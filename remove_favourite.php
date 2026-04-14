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
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$userId = $_SESSION['user_id'];

$recipeId = 0;
if (isset($_GET['recipe_id'])) {
    $recipeId = (int) $_GET['recipe_id'];
}

if ($recipeId > 0) {
    $stmt = $pdo->prepare("DELETE FROM Favourites WHERE userID = ? AND recipeID = ?");
    $stmt->execute([$userId, $recipeId]);
}

header("Location: user.php");
exit();