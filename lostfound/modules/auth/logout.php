<?php
// ============================================================
// modules/auth/logout.php
// C:/xampp/htdocs/lostfound/modules/auth/logout.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
startSecureSession();

if (isLoggedIn()) {
    logActivity('logout', 'auth', $_SESSION['user_id'] ?? null, 'user', 'User logged out');
}

$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;
