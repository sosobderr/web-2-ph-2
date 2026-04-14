<?php
require_once 'auth_check.php';
checkLogin('admin');

$host = 'localhost'; $db = 'nutrigood'; $user = 'root'; $pass = 'root';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) { die('DB error: ' . $e->getMessage()); }

$reportID       = isset($_POST['report_id'])        ? (int) $_POST['report_id']        : 0;
$reportedUserID = isset($_POST['reported_user_id']) ? (int) $_POST['reported_user_id'] : 0;
$action         = $_POST['report_action'] ?? '';

if ($action === 'block' && $reportedUserID > 0) {

    // 1. Get user info
    $stmt = $pdo->prepare("SELECT firstName, lastName, emailAddress FROM User WHERE id = ?");
    $stmt->execute([$reportedUserID]);
    $blockedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($blockedUser) {

        // 2. Get all recipes for this user
        $stmt = $pdo->prepare("SELECT id, photoFileName, videoFilePath FROM Recipe WHERE userID = ?");
        $stmt->execute([$reportedUserID]);
        $userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($userRecipes as $r) {
            $rid = $r['id'];

            $pdo->prepare("DELETE FROM Ingredients  WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM Instructions WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM Comment      WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM Likes        WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM Favourites   WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM Report       WHERE recipeID = ?")->execute([$rid]);

            // Delete photo file
            $photo = 'images/' . $r['photoFileName'];
            if (!empty($r['photoFileName']) && file_exists($photo)) unlink($photo);

            // Delete video file (local only)
            $video = $r['videoFilePath'];
            if (!empty($video) && file_exists($video) && strpos($video, 'http') !== 0) unlink($video);
        }

        // 3. Delete all recipes
        $pdo->prepare("DELETE FROM Recipe WHERE userID = ?")->execute([$reportedUserID]);

        // 4. Delete remaining user activity
        $pdo->prepare("DELETE FROM Likes      WHERE userID = ?")->execute([$reportedUserID]);
        $pdo->prepare("DELETE FROM Favourites WHERE userID = ?")->execute([$reportedUserID]);
        $pdo->prepare("DELETE FROM Report     WHERE userID = ?")->execute([$reportedUserID]);
        $pdo->prepare("DELETE FROM Comment    WHERE userID = ?")->execute([$reportedUserID]);

        // 5. Insert into BlockedUser
        $pdo->prepare("INSERT INTO BlockedUser (firstName, lastName, emailAddress) VALUES (?, ?, ?)")
            ->execute([$blockedUser['firstName'], $blockedUser['lastName'], $blockedUser['emailAddress']]);

        // 6. Delete user
        $pdo->prepare("DELETE FROM User WHERE id = ?")->execute([$reportedUserID]);
    }

} elseif ($action === 'dismiss' && $reportID > 0) {
    $pdo->prepare("DELETE FROM Report WHERE id = ?")->execute([$reportID]);
}

header('Location: admin.php');
exit();
