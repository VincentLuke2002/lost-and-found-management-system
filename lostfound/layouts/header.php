<?php
// ============================================================
// layouts/header.php
// Included at the top of every authenticated page
// Expects: $pageTitle (string)
// ============================================================
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/app.php';
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSecureSession();
requireLogin();

$user          = currentUser();
$flash         = getFlash();
$unreadCount   = getUnreadNotificationCount($user['id']);
$pageTitle     = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- MUST be first: apply dark class before anything renders to avoid flash -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50:'#f0f4ff', 100:'#e0eaff', 500:'#4361ee', 600:'#3451d1', 700:'#2a40b0' }
                    },
                    fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 font-sans text-gray-900 dark:text-gray-100">

<!-- Flash Message -->
<?php if ($flash): ?>
<div id="flash-msg" class="fixed top-4 right-4 z-50 max-w-sm w-full">
    <div class="flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg border
        <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
        <span class="text-sm font-medium"><?= e($flash['message']) ?></span>
        <button onclick="document.getElementById('flash-msg').remove()" class="ml-auto text-current opacity-60 hover:opacity-100">&times;</button>
    </div>
</div>
<script>setTimeout(()=>{const f=document.getElementById('flash-msg');if(f)f.remove();},4000);</script>
<?php endif; ?>

<div class="flex h-full min-h-screen">
