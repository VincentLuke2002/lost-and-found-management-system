<?php
// ============================================================
// modules/auth/login.php
// C:/xampp/htdocs/lostfound/modules/auth/login.php
// Browser: http://localhost/lostfound/modules/auth/login.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();

// Already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . '/modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? AND u.is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role_name'];
                $_SESSION['role_id']    = $user['role_id'];

                // Update last login
                $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity('login', 'auth', $user['id'], 'user', 'User logged in');

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect(BASE_URL . '/modules/dashboard/index.php');
            } else {
                $error = 'Invalid email or password.';
                logActivity('failed_login', 'auth', null, null, 'Failed login attempt: ' . $email);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{brand:{500:'#4361ee',600:'#3451d1'}},fontFamily:{sans:['Inter','ui-sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full bg-gray-50 font-sans flex items-center justify-center px-4">

<div class="w-full max-w-sm">

    <!-- Logo -->
    <div class="flex items-center justify-center gap-2.5 mb-8">
        <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center shadow-sm">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/>
            </svg>
        </div>
        <span class="text-xl font-semibold text-gray-900 tracking-tight"><?= APP_NAME ?></span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Sign in to your account</h2>
        <p class="text-sm text-gray-500 mb-6">Lost and Found Management System</p>

        <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="you@example.com">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <a href="<?= BASE_URL ?>/modules/auth/forgot_password.php" class="text-xs text-brand-500 hover:underline">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="••••••••">
            </div>

            <button type="submit"
                    class="w-full bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors mt-2">
                Sign in
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-500">
            Don't have an account?
            <a href="<?= BASE_URL ?>/modules/auth/register.php" class="text-brand-500 hover:underline font-medium">Register</a>
        </p>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6"><?= APP_NAME ?> &copy; <?= date('Y') ?></p>
</div>

</body>
</html>
