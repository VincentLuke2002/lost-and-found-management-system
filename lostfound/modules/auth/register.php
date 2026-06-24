<?php
// ============================================================
// modules/auth/register.php
// C:/xampp/htdocs/lostfound/modules/auth/register.php
// Browser: http://localhost/lostfound/modules/auth/register.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();

if (isLoggedIn()) redirect(BASE_URL . '/modules/dashboard/index.php');

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $values['full_name'] = trim($_POST['full_name'] ?? '');
        $values['email']     = trim($_POST['email'] ?? '');
        $values['phone']     = trim($_POST['phone'] ?? '');
        $password            = $_POST['password'] ?? '';
        $confirm             = $_POST['confirm_password'] ?? '';

        if (empty($values['full_name']))        $errors[] = 'Full name is required.';
        if (empty($values['email']))            $errors[] = 'Email is required.';
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if (strlen($password) < 8)              $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)             $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$values['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $ins  = $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, phone) VALUES (3, ?, ?, ?, ?)");
                $ins->execute([$values['full_name'], $values['email'], $hash, $values['phone']]);
                logActivity('register', 'auth', (int)$db->lastInsertId(), 'user', 'New user registered');
                setFlash('success', 'Account created! Please sign in.');
                redirect(BASE_URL . '/modules/auth/login.php');
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
    <title>Register — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{brand:{500:'#4361ee',600:'#3451d1'}},fontFamily:{sans:['Inter','ui-sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full bg-gray-50 font-sans flex items-center justify-center px-4 py-8">

<div class="w-full max-w-sm">
    <div class="flex items-center justify-center gap-2.5 mb-8">
        <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center shadow-sm">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/>
            </svg>
        </div>
        <span class="text-xl font-semibold text-gray-900 tracking-tight"><?= APP_NAME ?></span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Create an account</h2>
        <p class="text-sm text-gray-500 mb-6">Register to report lost or found items</p>

        <?php if ($errors): ?>
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $err): ?>
            <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                <input type="text" id="full_name" name="full_name" required
                       value="<?= e($values['full_name'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="Juan dela Cruz">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                <input type="email" id="email" name="email" required
                       value="<?= e($values['email'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="you@example.com">
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" id="phone" name="phone"
                       value="<?= e($values['phone'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="+63 9XX XXX XXXX">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="Min. 8 characters">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                       placeholder="Repeat password">
            </div>

            <button type="submit"
                    class="w-full bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors mt-2">
                Create account
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-500">
            Already have an account?
            <a href="<?= BASE_URL ?>/modules/auth/login.php" class="text-brand-500 hover:underline font-medium">Sign in</a>
        </p>
    </div>
</div>
</body>
</html>
