<?php
// ============================================================
// admin/users/edit.php
// C:/xampp/htdocs/lostfound/admin/users/edit.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin']);

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid user.'); redirect(BASE_URL . '/admin/users/index.php'); }

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();
if (!$targetUser) { setFlash('error','User not found.'); redirect(BASE_URL . '/admin/users/index.php'); }

$errors = [];
$values = $targetUser;
$roles  = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $values = array_merge($values, [
            'full_name'  => trim($_POST['full_name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'phone'      => trim($_POST['phone'] ?? ''),
            'role_id'    => (int)($_POST['role_id'] ?? $targetUser['role_id']),
            'department' => trim($_POST['department'] ?? ''),
            'is_active'  => (int)($_POST['is_active'] ?? 1),
        ]);
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (empty($values['full_name'])) $errors[] = 'Full name is required.';
        if (empty($values['email']))     $errors[] = 'Email is required.';
        if ($newPassword && strlen($newPassword) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($newPassword && $newPassword !== $confirm)  $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $emailCheck = $db->prepare("SELECT id FROM users WHERE email=? AND id != ?");
            $emailCheck->execute([$values['email'], $id]);
            if ($emailCheck->fetch()) {
                $errors[] = 'Email already used by another account.';
            } else {
                if ($newPassword) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("UPDATE users SET full_name=?, email=?, phone=?, role_id=?, department=?, is_active=?, password_hash=? WHERE id=?")
                       ->execute([$values['full_name'], $values['email'], $values['phone'] ?: null, $values['role_id'], $values['department'] ?: null, $values['is_active'], $hash, $id]);
                } else {
                    $db->prepare("UPDATE users SET full_name=?, email=?, phone=?, role_id=?, department=?, is_active=? WHERE id=?")
                       ->execute([$values['full_name'], $values['email'], $values['phone'] ?: null, $values['role_id'], $values['department'] ?: null, $values['is_active'], $id]);
                }
                logActivity('update', 'users', $id, 'user', 'Updated user: ' . $values['email']);
                setFlash('success', 'User updated.');
                redirect(BASE_URL . '/admin/users/index.php');
            }
        }
    }
}

$pageTitle = 'Edit User';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="max-w-xl">
    <a href="<?= BASE_URL ?>/admin/users/index.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Users
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">Edit User</h2>

        <?php if ($errors): ?>
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" value="<?= e($values['full_name']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="<?= e($values['email']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= e($values['phone'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Department</label>
                    <input type="text" name="department" value="<?= e($values['department'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Role</label>
                    <select name="role_id" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $values['role_id'] == $r['id'] ? 'selected' : '' ?>><?= ucfirst($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                    <select name="is_active" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="1" <?= $values['is_active'] ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= !$values['is_active'] ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">New Password <span class="text-gray-400 font-normal">(leave blank to keep)</span></label>
                    <input type="password" name="new_password" minlength="8"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                           placeholder="Min. 8 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm New Password</label>
                    <input type="password" name="confirm_password"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors">Save Changes</button>
                <a href="<?= BASE_URL ?>/admin/users/index.php" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
