<?php
require_once 'auth_check.php';
checkLogin('user');

$host = 'localhost';
$db = 'nutrigood';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function convert_youtube_url_to_embed(string $videoUrl): string
{
    $videoUrl = trim($videoUrl);

    if (strpos($videoUrl, 'youtu.be/') !== false) {
        $path = trim(parse_url($videoUrl, PHP_URL_PATH) ?? '', '/');
        $videoID = explode('/', $path)[0] ?? '';

        if ($videoID !== '') {
            return 'https://www.youtube.com/embed/' . $videoID;
        }
    }

    if (strpos($videoUrl, 'watch?v=') !== false) {
        parse_str(parse_url($videoUrl, PHP_URL_QUERY) ?? '', $query);

        if (!empty($query['v'])) {
            return 'https://www.youtube.com/embed/' . $query['v'];
        }
    }

    return $videoUrl;
}

function ensure_directory_exists(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
        die('Failed to create upload folder: ' . $directory);
    }
}

function delete_local_file_if_exists(?string $path): void
{
    if (empty($path) || strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return;
    }

    if (file_exists($path)) {
        unlink($path);
    }
}

function delete_old_photo_if_exists(?string $photoFileName): void
{
    if (empty($photoFileName)) {
        return;
    }

    $path = 'uploads/photos/' . $photoFileName;

    if (file_exists($path)) {
        unlink($path);
    }
}

function save_uploaded_photo_file(array $file, int $userID): string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Photo upload error. Code: ' . $file['error']);
    }

    $directory = 'uploads/photos';
    $filesystemDirectory = __DIR__ . '/' . $directory;
    ensure_directory_exists($filesystemDirectory);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        die('Invalid photo type. Allowed types: jpg, jpeg, png, gif, webp');
    }

    $safeName = uniqid('recipe_' . $userID . '_', true) . '.' . $extension;
    $destination = $filesystemDirectory . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        die('Failed to upload recipe photo. Check uploads/photos folder permissions.');
    }

    return $safeName;
}

function save_uploaded_video_file(array $file, int $userID): string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Video upload error. Code: ' . $file['error']);
    }

    $directory = 'uploads/videos';
    $filesystemDirectory = __DIR__ . '/' . $directory;
    ensure_directory_exists($filesystemDirectory);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['mp4', 'mov', 'webm', 'ogg'];

    if (!in_array($extension, $allowedExtensions, true)) {
        die('Invalid video type. Allowed types: mp4, mov, webm, ogg');
    }

    $safeName = uniqid('video_' . $userID . '_', true) . '.' . $extension;
    $destination = $filesystemDirectory . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        die('Failed to upload video file. Check uploads/videos folder permissions.');
    }

    return $directory . '/' . $safeName;
}

function has_valid_ingredient(array $names, array $quantities): bool
{
    for ($i = 0; $i < count($names); $i++) {
        if (trim($names[$i]) !== '' && trim($quantities[$i] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

function has_valid_instruction(array $steps): bool
{
    foreach ($steps as $step) {
        if (trim($step) !== '') {
            return true;
        }
    }

    return false;
}

function require_existing_user(PDO $pdo, int $userID): void
{
    $stmt = $pdo->prepare("SELECT id FROM User WHERE id = ? AND userType = 'user'");
    $stmt->execute([$userID]);

    if (!$stmt->fetch()) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        header('Location: login.html?error=' . urlencode('Your login session expired. Please log in again.'));
        exit();
    }
}

$recipeID = (int) ($_POST['recipeID'] ?? $_GET['id'] ?? 0);
$userID = (int) $_SESSION['user_id'];
require_existing_user($pdo, $userID);

if ($recipeID <= 0) {
    die('Recipe ID is missing.');
}

$sql = "SELECT * FROM Recipe WHERE id = ? AND userID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$recipeID, $userID]);
$recipe = $stmt->fetch();

if (!$recipe) {
    die('Recipe not found.');
}

$sql = "SELECT id, categoryName FROM RecipeCategory ORDER BY categoryName ASC";
$result = $pdo->query($sql);
$categories = $result->fetchAll();

$sql = "SELECT * FROM Ingredients WHERE recipeID = ? ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$recipeID]);
$ingredients = $stmt->fetchAll();

$sql = "SELECT * FROM Instructions WHERE recipeID = ? ORDER BY stepOrder ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$recipeID]);
$instructions = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['recipeName'] ?? '');
    $categoryID = (int) ($_POST['category'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    $ingredientNames = $_POST['ingredientName'] ?? [];
    $ingredientQtys = $_POST['ingredientQty'] ?? [];
    $instructionSteps = $_POST['instructionStep'] ?? [];

    if ($name === '' || $categoryID <= 0 || $description === '') {
        die('Please fill in all required fields.');
    }

    if (!has_valid_ingredient($ingredientNames, $ingredientQtys)) {
        die('Please add at least one ingredient with quantity.');
    }

    if (!has_valid_instruction($instructionSteps)) {
        die('Please add at least one instruction step.');
    }

    $oldPhoto = $recipe['photoFileName'];
    $oldVideo = $recipe['videoFilePath'];

    $newPhotoName = $oldPhoto;
    $newVideoPath = $oldVideo;
    $oldPhotoToDelete = null;
    $oldVideoToDelete = null;
    $uploadedPhotoToDeleteOnError = null;
    $uploadedVideoToDeleteOnError = null;

    $uploadedPhotoName = save_uploaded_photo_file($_FILES['recipePhoto'] ?? [], $userID);
    if ($uploadedPhotoName !== '') {
        $newPhotoName = $uploadedPhotoName;
        $oldPhotoToDelete = $oldPhoto;
        $uploadedPhotoToDeleteOnError = 'uploads/photos/' . $uploadedPhotoName;
    }

    if (isset($_FILES['videoFile']) && $_FILES['videoFile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $newVideoPath = save_uploaded_video_file($_FILES['videoFile'], $userID);
        $oldVideoToDelete = $oldVideo;
        $uploadedVideoToDeleteOnError = $newVideoPath;
    } elseif (trim($_POST['videoUrl'] ?? '') !== '') {
        $newVideoPath = convert_youtube_url_to_embed($_POST['videoUrl']);
        $oldVideoToDelete = $oldVideo;
    }

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE Recipe
                SET categoryID = ?, name = ?, description = ?, photoFileName = ?, videoFilePath = ?
                WHERE id = ? AND userID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $categoryID,
            $name,
            $description,
            $newPhotoName,
            $newVideoPath,
            $recipeID,
            $userID
        ]);

        $sql = "DELETE FROM Ingredients WHERE recipeID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$recipeID]);

        $sql = "INSERT INTO Ingredients (recipeID, ingredientName, ingredientQuantity) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        for ($i = 0; $i < count($ingredientNames); $i++) {
            $ingredientName = trim($ingredientNames[$i]);
            $ingredientQty = trim($ingredientQtys[$i] ?? '');

            if ($ingredientName !== '' && $ingredientQty !== '') {
                $stmt->execute([$recipeID, $ingredientName, $ingredientQty]);
            }
        }

        $sql = "DELETE FROM Instructions WHERE recipeID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$recipeID]);

        $sql = "INSERT INTO Instructions (recipeID, step, stepOrder) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $stepOrder = 1;
        foreach ($instructionSteps as $step) {
            $step = trim($step);

            if ($step !== '') {
                $stmt->execute([$recipeID, $step, $stepOrder]);
                $stepOrder++;
            }
        }

        $pdo->commit();

        delete_old_photo_if_exists($oldPhotoToDelete);
        delete_local_file_if_exists($oldVideoToDelete);
    } catch (Throwable $e) {
        $pdo->rollBack();
        delete_local_file_if_exists($uploadedPhotoToDeleteOnError);
        delete_local_file_if_exists($uploadedVideoToDeleteOnError);
        die('Failed to update recipe: ' . $e->getMessage());
    }

    header('Location: Myrecipes.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NutriGood - Edit Recipe</title>

<style>
* {
    box-sizing: border-box;
    font-family: "cooper BT", "Times New Roman", serif;
}

body {
    margin: 0;
    background-color: #fbfbf3;
    color: #1f1f1f;
}

.header {
    padding: 20px 40px;
    border-bottom: 1px solid #9fa59a;
}

.logo {
    font-size: 22px;
    font-weight: bold;
    color: #3b4a2f;
}

.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 40px;
}

h2 {
    color: #22381F;
    margin-bottom: 30px;
    font-size: 40px;
    font-family: 'Cooper BT', Georgia, serif;
    font-weight: 400;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: inline-block;
    width: 200px;
    vertical-align: top;
    font-size: 18px;
    font-family: 'Cooper BT', Georgia, serif;
    font-weight: 600;
}

input[type="text"],
textarea,
select {
    width: 320px;
    padding: 10px 12px;
    border: 1px solid rgba(34,56,31,.35);
    background-color: #fff;
    font-size: 16px;
    font-family: 'Cooper BT', Georgia, serif;
    border-radius: 8px;
}

textarea {
    height: 100px;
    resize: none;
}

input[type="file"] {
    border: none;
    font-size: 16px;
    font-family: 'Cooper BT', Georgia, serif;
}

.inline-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: 160px;
}

.inline-group label {
    width: auto;
    margin-right: 15px;
}

.inline-group input {
    width: 200px;
    font-size: 16px;
}

.add-btn {
    padding: 6px 14px;
    border: 1px solid rgba(34,56,31,.55);
    background-color: #22381F;
    color: #FFFFFA;
    cursor: pointer;
    font-size: 16px;
    border-radius: 6px;
    font-family: 'Cooper BT', Georgia, serif;
    transition: all 0.2s ease;
}

.add-btn:hover {
    background-color: #245230;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.remove-btn {
    padding: 6px 14px;
    border: 1px solid rgba(220, 38, 38, 0.55);
    background-color: #dc2626;
    color: #FFFFFA;
    cursor: pointer;
    font-size: 16px;
    border-radius: 6px;
    font-family: 'Cooper BT', Georgia, serif;
    transition: all 0.2s ease;
}

.remove-btn:hover {
    background-color: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.submit-wrapper {
    margin-bottom: 39px;
    text-align: center;
}

.submit-btn {
    padding: 12px 30px;
    border-radius: 25px;
    border: 2px solid #22381F;
    background-color: #22381F;
    color: #FFFFFA;
    font-size: 18px;
    cursor: pointer;
    font-family: 'Cooper BT', Georgia, serif;
    font-weight: 600;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    background-color: #245230;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.footer {
    margin-top: 60px;
    background-color: #f4f4ec;
    padding: 20px;
    text-align: center;
}

.footer-logo {
    font-size: 20px;
    font-weight: bold;
    color: #3b4a2f;
}

.footer p {
    margin: 6px 0;
    font-size: 14px;
}

.topbar, .topbar-admin {
    background: var(--cream);
    border-bottom: 1px solid var(--soft-border);
}

.topbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 0;
}

.pill {
    border: 2px solid var(--primary-green);
    background: var(--primary-green);
    color: var(--white);
    padding: 12px 30px;
    border-radius: 25px;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-family: 'Cooper BT', Georgia, serif;
    transition: all 0.3s ease;
    cursor: pointer;
}

.pill:hover {
    background-color: #245230;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.pill:active {
    transform: scale(.98);
}

.photo-row {
    display: flex;
    gap: 40px;
    align-items: flex-start;
    justify-content: space-between;
}

.photo-preview-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
    min-width: 200px;
}

.photo-preview-section strong {
    font-size: 16px;
    color: #22381F;
    font-weight: 600;
}

.current-img {
    width: 200px;
    height: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.current-img img {
    width: 50%;
    height: 90%;
    object-fit: cover;
    opacity: 0.9;
    border-radius: 10px;
}

.video-row {
    display: flex;
    gap: 40px;
    align-items: flex-start;
    margin-top: 25px;
}

.video-input-section {
    flex: 1;
}

.video-preview-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 200px;
    align-items: center;
}

.video-preview-section strong {
    font-size: 16px;
    color: #22381F;
    font-weight: 600;
}

.current-video {
    width: 200px;
    height: 110px;
    background-color: #000;
    border-radius: 8px;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #fff;
}
</style>

<link rel="stylesheet" href="style.css">
</head>

<body>
<header class="topbar-admin">
    <div class="containers">
        <div class="topbar-inner">
            <div class="logo-section">
                <img src="images/nutrigood-logo.png" alt="NutriGood Logo" class="logo">
            </div>
        </div>
    </div>
</header>

<div class="container">
    <h2>Edit recipe</h2>

    <form id="editRecipeForm" method="POST" action="edit_recipe.php?id=<?= htmlspecialchars($recipeID) ?>" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="134217728">
        <input type="hidden" name="recipeID" value="<?= htmlspecialchars($recipe['id']) ?>">

        <div class="form-group">
            <label>Name: <span style="color: red;">*</span></label>
            <input type="text" name="recipeName" value="<?= htmlspecialchars($recipe['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>Category: <span style="color: red;">*</span></label>
            <select name="category" required>
                <option value="">-- Select Category --</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category['id']) ?>" <?= ($recipe['categoryID'] == $category['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['categoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Description: <span style="color: red;">*</span></label>
            <textarea name="description" required><?= htmlspecialchars($recipe['description']) ?></textarea>
        </div>

        <div class="photo-row">
            <div class="form-group">
                <label>Upload recipe photo:</label>
                <input type="file" name="recipePhoto" accept="image/*">
            </div>

            <div class="photo-preview-section">
                <strong>Current photo:</strong>
                <div class="current-img">
                    <?php if (!empty($recipe['photoFileName'])): ?>
                        <img src="uploads/photos/<?= htmlspecialchars($recipe['photoFileName']) ?>" alt="current image">
                    <?php else: ?>
                        No photo
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top:25px;">
            <label>Ingredients: <span style="color: red;">*</span></label>
        </div>

        <div id="ingredientsContainer">
            <?php if (count($ingredients) > 0): ?>
                <?php foreach ($ingredients as $index => $ingredient): ?>
                    <div class="form-group inline-group">
                        <label>Ingredient <?= $index + 1 ?>:</label>
                        <input type="text" name="ingredientName[]" placeholder="Name" value="<?= htmlspecialchars($ingredient['ingredientName']) ?>">
                        <input type="text" name="ingredientQty[]" placeholder="Quantity" value="<?= htmlspecialchars($ingredient['ingredientQuantity']) ?>">
                        <button type="button" class="remove-btn" onclick="removeElement(this)">-</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="form-group inline-group">
                <label>Ingredient <?= count($ingredients) + 1 ?>:</label>
                <input type="text" name="ingredientName[]" placeholder="Name">
                <input type="text" name="ingredientQty[]" placeholder="Quantity">
                <button type="button" class="add-btn" onclick="addIngredient()">+</button>
            </div>
        </div>

        <div class="form-group" style="margin-top:25px;">
            <label>Instructions: <span style="color: red;">*</span></label>
        </div>

        <div id="instructionsContainer">
            <?php if (count($instructions) > 0): ?>
                <?php foreach ($instructions as $index => $instruction): ?>
                    <div class="form-group inline-group">
                        <label>Step <?= $index + 1 ?>:</label>
                        <input type="text" name="instructionStep[]" style="width:380px;" value="<?= htmlspecialchars($instruction['step']) ?>">
                        <button type="button" class="remove-btn" onclick="removeElement(this)">-</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="form-group inline-group">
                <label>Step <?= count($instructions) + 1 ?>:</label>
                <input type="text" name="instructionStep[]" style="width:380px;">
                <button type="button" class="add-btn" onclick="addInstruction()">+</button>
            </div>
        </div>

        <div class="form-group" style="margin-top:25px;">
            <label>Upload video file:</label>
            <input type="file" name="videoFile" accept="video/mp4,video/quicktime,video/webm,video/ogg">
        </div>

        <div class="video-row">
            <div class="video-input-section">
                <div class="form-group">
                    <label>Video URL:</label>
                    <input type="text" name="videoUrl" placeholder="https://youtube.com/...">
                </div>
            </div>

            <div class="video-preview-section">
                <strong>Current video:</strong>
                <div class="current-video">
                    <?php
                    $video = trim($recipe['videoFilePath'] ?? '');

                    if (!empty($video)) {
                        if (strpos($video, 'youtu.be/') !== false) {
                            $videoID = substr($video, strrpos($video, '/') + 1);
                            $video = "https://www.youtube.com/embed/" . $videoID;
                        } elseif (strpos($video, 'watch?v=') !== false) {
                            parse_str(parse_url($video, PHP_URL_QUERY), $query);

                            if (!empty($query['v'])) {
                                $video = "https://www.youtube.com/embed/" . $query['v'];
                            }
                        }

                        if (strpos($video, 'youtube.com/embed/') !== false) {
                            ?>
                            <iframe
                                width="200"
                                height="110"
                                src="<?= htmlspecialchars($video) ?>"
                                frameborder="0"
                                allowfullscreen>
                            </iframe>
                            <?php
                        } elseif (
                            str_starts_with($video, 'uploads/')
                            || str_ends_with(strtolower($video), '.mp4')
                            || str_ends_with(strtolower($video), '.mov')
                            || str_ends_with(strtolower($video), '.webm')
                            || str_ends_with(strtolower($video), '.ogg')
                        ) {
                            ?>
                            <video width="200" height="110" controls>
                                <source src="<?= htmlspecialchars($video) ?>">
                                Your browser does not support the video tag.
                            </video>
                            <?php
                        } else {
                            ?>
                            <a href="<?= htmlspecialchars($video) ?>" target="_blank">View Video</a>
                            <?php
                        }
                    } else {
                        echo 'No video';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="submit-wrapper">
            <button type="submit" class="submit-btn">Update recipe</button>
        </div>
    </form>
</div>

<section class="brand-banner">
    <div class="banner-content"></div>
</section>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-divider"></div>
            <p class="footer-copyright">&copy; 2026 NutriGood</p>
            <a href="mailto:NutriGood@gmail.com" class="footer-contact">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="email-icon">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
                NutriGood@gmail.com
            </a>

            <div class="footer-social">
                <a href="#" class="social-link" aria-label="LinkedIn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                    </svg>
                </a>
                <a href="#" class="social-link" aria-label="Twitter">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                    </svg>
                </a>
                <a href="#" class="social-link" aria-label="TikTok">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                    </svg>
                </a>
                <span class="footer-brand-text">NutriGood</span>
            </div>
        </div>
    </div>
</footer>

<script>
function getIngredientCount() {
    return document.querySelectorAll('#ingredientsContainer .inline-group').length;
}

function getInstructionCount() {
    return document.querySelectorAll('#instructionsContainer .inline-group').length;
}

function addIngredient() {
    const container = document.getElementById('ingredientsContainer');
    const currentCount = getIngredientCount();
    const lastItem = container.querySelector('.inline-group:last-child');
    const qtyInput = lastItem.querySelectorAll('input[name="ingredientQty[]"]')[0];

    if (qtyInput.value.trim() !== '') {
        const numericValue = parseFloat(qtyInput.value);

        if (isNaN(numericValue) || numericValue <= 0) {
            alert('Quantity must start with a valid positive number! (e.g., 2, 2.5, 2 cups, 500g)');
            qtyInput.focus();
            return;
        }
    }

    const addBtn = lastItem.querySelector('.add-btn');
    if (addBtn) {
        addBtn.remove();
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-btn';
        removeBtn.textContent = '-';
        removeBtn.onclick = function() { removeElement(this); };
        lastItem.appendChild(removeBtn);
    }

    const newIngredient = document.createElement('div');
    newIngredient.className = 'form-group inline-group';
    newIngredient.innerHTML = `
        <label>Ingredient ${currentCount + 1}:</label>
        <input type="text" name="ingredientName[]" placeholder="Name">
        <input type="text" name="ingredientQty[]" placeholder="Quantity">
        <button type="button" class="add-btn" onclick="addIngredient()">+</button>
    `;

    container.appendChild(newIngredient);
}

function addInstruction() {
    const container = document.getElementById('instructionsContainer');
    const currentCount = getInstructionCount();
    const lastItem = container.querySelector('.inline-group:last-child');
    const addBtn = lastItem.querySelector('.add-btn');

    if (addBtn) {
        addBtn.remove();
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-btn';
        removeBtn.textContent = '-';
        removeBtn.onclick = function() { removeElement(this); };
        lastItem.appendChild(removeBtn);
    }

    const newInstruction = document.createElement('div');
    newInstruction.className = 'form-group inline-group';
    newInstruction.innerHTML = `
        <label>Step ${currentCount + 1}:</label>
        <input type="text" name="instructionStep[]" style="width:380px;">
        <button type="button" class="add-btn" onclick="addInstruction()">+</button>
    `;

    container.appendChild(newInstruction);
}

function removeElement(button) {
    const parent = button.closest('#ingredientsContainer, #instructionsContainer');
    const items = parent.querySelectorAll('.inline-group');

    if (items.length <= 1) {
        alert('You must have at least one item!');
        return;
    }

    button.parentElement.remove();
    updateLabels();
}

function updateLabels() {
    const ingredients = document.querySelectorAll('#ingredientsContainer .inline-group');
    ingredients.forEach((item, index) => {
        item.querySelector('label').textContent = `Ingredient ${index + 1}:`;
    });

    const instructions = document.querySelectorAll('#instructionsContainer .inline-group');
    instructions.forEach((item, index) => {
        item.querySelector('label').textContent = `Step ${index + 1}:`;
    });
}
</script>
</body>
</html>
