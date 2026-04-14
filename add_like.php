<?php
require_once 'auth_check.php';
checkLogin('user');

$host = 'localhost'; $db = 'nutrigood'; $user = 'root'; $pass = 'root';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) { die('DB error: ' . $e->getMessage()); }

$userID   = $_SESSION['user_id'];
$recipeID = isset($_POST['recipe_id']) ? (int) $_POST['recipe_id'] : 0;

if ($recipeID > 0) {
    // Make sure user is not the creator
    $stmt = $pdo->prepare("SELECT userID FROM Recipe WHERE id = ?");
    $stmt->execute([$recipeID]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recipe && $recipe['userID'] != $userID) {
        $check = $pdo->prepare("SELECT 1 FROM Likes WHERE userID = ? AND recipeID = ?");
        $check->execute([$userID, $recipeID]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO Likes (userID, recipeID) VALUES (?, ?)")->execute([$userID, $recipeID]);
        }
    }
}

header("Location: view-recipe.php?id=$recipeID");
exit();
