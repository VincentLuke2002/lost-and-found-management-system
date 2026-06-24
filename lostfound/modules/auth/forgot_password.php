<?php
// ============================================================
// modules/auth/forgot_password.php
// C:/xampp/htdocs/lostfound/modules/auth/forgot_password.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();

if (isLoggedIn()) redirect(BASE_URL . '/modules/dashboard/index.php');

$message = '';
$type    = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $type    = 'error';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $type    = 'error';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
                   ->execute([$token, $expires, $user['id']]);
                // NOTE: Email sending requires a mail server (e.g. Mailtrap or SMTP).
                // For development, the reset link is shown directly below.
                // In production, email this link to the user.
                $resetLink = BASE_URL . '/modules/auth/reset_password.php?token=' . $token;
                $message = 'Reset link generated. Dev link: <a href="' . $resetLink . '" class="underline">' . $resetLink . '</a>';
            } else {
                // Don't reveal if email exists
                $message = 'If that email is registered, a reset link has been sent.';
            }
            $type = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{brand:{500:'#4361ee',600:'#3451d1'}},fontFamily:{sans:['Inter','ui-sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full bg-gray-50 font-sans flex items-center justify-center px-4">
<div class="w-full max-w-sm">
    <div class="flex items-center justify-center gap-2.5 mb-8">
        <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/>
            </svg>
        </div>
        <span class="text-xl font-semibold text-gray-900"><?= APP_NAME ?></span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Reset your password</h2>
        <p class="text-sm text-gray-500 mb-6">Enter your email and we'll send a reset link.</p>

        <?php if ($message): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm
            <?= $type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                <input type="email" id="email" name="email" required
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="you@example.com">
            </div>
            <button type="submit"
                    class="w-full bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
                Send reset link
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-500">
            <a href="<?= BASE_URL ?>/modules/auth/login.php" class="text-brand-500 hover:underline">Back to sign in</a>
        </p>
    </div>
</div>
</body>
</html>
