<?php
// ============================================================
// index.php  (C:/xampp/htdocs/lostfound/index.php)
// Entry point — redirects based on login state
// ============================================================
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
}
exit;
