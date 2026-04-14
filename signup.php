<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.html');
    exit();
}

$host = 'localhost'; $db = 'nutrigood'; $user = 'root'; $pass = 'root';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) { die('DB error: ' . $e->getMessage()); }

$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName']  ?? '');
$email     = trim($_POST['email']     ?? '');
$password  = $_POST['password']       ?? '';

if (!$firstName || !$lastName || !$email || !$password) {
    header('Location: signup.html?error=' . urlencode('Please fill in all required fields.'));
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: signup.html?error=' . urlencode('Invalid email address.'));
    exit();
}

$stmt = $pdo->prepare('SELECT id FROM User WHERE emailAddress = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: signup.html?error=' . urlencode('This email address is already registered. Please log in.'));
    exit();
}
$stmt = $pdo->prepare('SELECT id FROM BlockedUser WHERE emailAddress = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: signup.html?error=' . urlencode('This email address is not allowed to register.'));
    exit();
}

// Insert user with default photo first to get the ID
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO User (userType, firstName, lastName, emailAddress, password, photoFileName) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['user', $firstName, $lastName, $email, $hashedPassword, 'default.png']);
$newUserId = (int) $pdo->lastInsertId();

// Handle photo upload using the user ID in the filename
if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/images/uploads/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

    $ext = strtolower(pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $finalFileName = 'user_' . $newUserId . '.' . $ext;
        if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadDir . $finalFileName)) {
            $stmt = $pdo->prepare('UPDATE User SET photoFileName = ? WHERE id = ?');
            $stmt->execute(['images/uploads/' . $finalFileName, $newUserId]);
        }
    }
}

$_SESSION['user_id']   = $newUserId;
$_SESSION['user_type'] = 'user';
header('Location: user.php');
exit();
