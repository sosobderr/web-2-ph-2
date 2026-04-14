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

$recipeID = (int) ($_GET['id'] ?? 0);
$userID = (int) $_SESSION['user_id'];

if ($recipeID <= 0) {
    header('Location: Myrecipes.php');
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, videoFilePath FROM Recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);
$recipe = $stmt->fetch();

if (!$recipe || empty($recipe['videoFilePath'])) {
    header('Location: Myrecipes.php');
    exit();
}

$video = convert_youtube_url_to_embed($recipe['videoFilePath']);
$lowerVideo = strtolower($video);
$isYoutube = strpos($video, 'youtube.com/embed/') !== false;
$isLocalVideo = (
    str_starts_with($video, 'uploads/videos/')
    || str_ends_with($lowerVideo, '.mp4')
    || str_ends_with($lowerVideo, '.mov')
    || str_ends_with($lowerVideo, '.webm')
    || str_ends_with($lowerVideo, '.ogg')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriGood - Watch Video</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            margin: 0;
            background-color: #fbfbf3;
            color: #22381F;
            font-family: 'Cooper BT', Georgia, serif;
        }

        .watch-wrap {
            max-width: 1050px;
            margin: 45px auto;
            padding: 0 30px;
        }

        .watch-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0;
            font-size: 36px;
            font-weight: 700;
        }

        .back-link {
            color: #22381F;
            font-weight: 700;
            text-decoration: underline;
        }

        .video-large {
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
        }

        .video-large iframe,
        .video-large video {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .plain-link {
            display: inline-block;
            margin-top: 18px;
            color: #22381F;
            font-size: 20px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="watch-wrap">
        <div class="watch-top">
            <h1><?= htmlspecialchars($recipe['name']) ?></h1>
            <a class="back-link" href="Myrecipes.php">Back to My Recipes</a>
        </div>

        <div class="video-large">
            <?php if ($isYoutube): ?>
                <iframe
                    src="<?= htmlspecialchars($video) ?>"
                    title="Recipe video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            <?php elseif ($isLocalVideo): ?>
                <video controls autoplay>
                    <source src="<?= htmlspecialchars($video) ?>">
                    Your browser does not support the video tag.
                </video>
            <?php else: ?>
                <a class="plain-link" href="<?= htmlspecialchars($video) ?>" target="_blank">Open video</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
