<?php
session_start();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit();
}

// ── Database connection ──────────────────────────────────────────────────────
$host    = 'localhost';
$db      = 'nutrigood';
$user    = 'root';
$pass    = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// ── Collect inputs ───────────────────────────────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if (!$email || !$password) {
    header('Location: login.html?error=' . urlencode('Please enter your email and password.'));
    exit();
}

// ── Check if user is blocked ─────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM BlockedUser WHERE emailAddress = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: login.html?error=' . urlencode('Your account has been blocked. Please contact support.'));
    exit();
}

// ── Fetch user from database ─────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, userType, firstName, password FROM User WHERE emailAddress = ?');
$stmt->execute([$email]);
$userRow = $stmt->fetch();

// ── Verify password ──────────────────────────────────────────────────────────
if (!$userRow || !password_verify($password, $userRow['password'])) {
    header('Location: login.html?error=' . urlencode('Incorrect email address or password.'));
    exit();
}

// ── Login successful – set session variables ──────────────────────────────────
$_SESSION['user_id']   = (int) $userRow['id'];
$_SESSION['user_type'] = $userRow['userType'];

// ── Redirect based on user type ───────────────────────────────────────────────
if ($userRow['userType'] === 'admin') {
    header('Location: admin.php');
} else {
    header('Location: user.php');
}
exit();
