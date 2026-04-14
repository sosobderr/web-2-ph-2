<?php
/**
 * auth_check.php
 * ─────────────────────────────────────────────────────────────
 * Include this file at the TOP of every protected page.
 *
 * Usage (regular user pages):
 *   require_once 'auth_check.php';
 *   checkLogin();          // redirects to login if not logged in
 *
 * Usage (admin-only pages):
 *   require_once 'auth_check.php';
 *   checkLogin('admin');   // redirects to login if not logged in OR not admin
 *
 * Usage (user-only pages):
 *   require_once 'auth_check.php';
 *   checkLogin('user');    // redirects to login if not logged in OR not a regular user
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures the visitor is logged in (and optionally matches a required userType).
 *
 * @param string|null $requiredType  'user', 'admin', or null (any authenticated user)
 */
function checkLogin(?string $requiredType = null): void
{
    // Not logged in at all
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_type'])) {
        header('Location: login.html?error=' . urlencode('Please log in to access this page.'));
        exit();
    }

    // Wrong user type
    if ($requiredType !== null && $_SESSION['user_type'] !== $requiredType) {
        $msg = ($requiredType === 'admin')
            ? 'Admin access required.'
            : 'You must be a regular user to access this page.';
        header('Location: login.html?error=' . urlencode($msg));
        exit();
    }
}
