<?php
require_once 'auth_check.php';
checkLogin('user');

$host = 'localhost'; $db = 'nutrigood'; $user = 'root'; $pass = 'root';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) { die('DB error: ' . $e->getMessage()); }

$userID      = $_SESSION['user_id'];
$recipeID    = isset($_POST['recipe_id']) ? (int) $_POST['recipe_id'] : 0;
$commentText = trim($_POST['comment'] ?? '');

if ($recipeID > 0 && $commentText !== '') {
    $pdo->prepare("INSERT INTO Comment (recipeID, userID, comment) VALUES (?, ?, ?)")
        ->execute([$recipeID, $userID, $commentText]);
}

header("Location: view-recipe.php?id=$recipeID");
exit();
