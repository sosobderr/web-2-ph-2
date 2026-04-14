<?php
require_once 'auth_check.php';
checkLogin('user');

// ── Database connection ──────────────────────────────────────────────────────
$host    = 'localhost';
$db      = 'nutrigood';
$user    = 'root';
$pass    = 'root';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$userID   = $_SESSION['user_id'];
$recipeID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($recipeID <= 0) {
    header('Location: Myrecipes.php');
    exit();
}

// ── Make sure the recipe belongs to this user ────────────────────────────────
$stmt = $pdo->prepare("SELECT id, photoFileName, videoFilePath FROM Recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);
$recipe = $stmt->fetch();

if (!$recipe) {
    // Recipe not found or doesn't belong to this user
    header('Location: Myrecipes.php');
    exit();
}

// ── Cascading delete ─────────────────────────────────────────────────────────

// 1. Delete ingredients
$stmt = $pdo->prepare("DELETE FROM Ingredients WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 2. Delete instructions
$stmt = $pdo->prepare("DELETE FROM Instructions WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 3. Delete comments
$stmt = $pdo->prepare("DELETE FROM Comment WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 4. Delete likes
$stmt = $pdo->prepare("DELETE FROM Likes WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 5. Delete favourites
$stmt = $pdo->prepare("DELETE FROM Favourites WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 6. Delete reports
$stmt = $pdo->prepare("DELETE FROM Report WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 7. Delete photo file from server (if not default)
$photoPath = 'images/' . $recipe['photoFileName'];
if (!empty($recipe['photoFileName']) && file_exists($photoPath)) {
    unlink($photoPath);
}

// 8. Delete video file from server (only if it's a local file, not a URL)
$videoPath = $recipe['videoFilePath'];
if (!empty($videoPath) && file_exists($videoPath) && strpos($videoPath, 'http') !== 0) {
    unlink($videoPath);
}

// 9. Finally, delete the recipe itself
$stmt = $pdo->prepare("DELETE FROM Recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);

// ── Redirect back ─────────────────────────────────────────────────────────────
header('Location: Myrecipes.php');
exit();
