<?php
// ============================================================
// admin/users/index.php
// C:/xampp/htdocs/lostfound/admin/users/index.php
// Browser: http://localhost/lostfound/admin/users/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin']);

$db     = getDB();
$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $s        = "%{$search}%";
    $params   = array_merge($params, [$s, $s]);
}
if ($role !== '') {
    $where[]  = 'r.name = ?';
    $params[] = $role;
}

$whereSQL   = implode(' AND ', $where);
$countStmt  = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE {$whereSQL}");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $page);

$users = $db->prepare("SELECT u.*, r.name AS role_name
    FROM users u JOIN roles r ON u.role_id = r.id
    WHERE {$whereSQL}
    ORDER BY u.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$users->execute($params);
$users = $users->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();

// Toggle active
if (isset($_GET['toggle']) && isset($_GET['csrf'])) {
    if (verifyCSRF($_GET['csrf'])) {
        $uid = (int)$_GET['toggle'];
        if ($uid !== currentUser()['id']) {
            $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$uid]);
            setFlash('success','User status updated.');
        }
    }
    redirect(BASE_URL . '/admin/users/index.php');
}

// Change role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    if (verifyCSRF($_POST['csrf_token'] ?? '')) {
        $uid    = (int)$_POST['user_id'];
        $roleId = (int)$_POST['role_id'];
        if ($uid !== currentUser()['id']) {
            $db->prepare("UPDATE users SET role_id=? WHERE id=?")->execute([$roleId, $uid]);
            logActivity('update', 'users', $uid, 'user', 'Changed role for user #' . $uid);
            setFlash('success','User role updated.');
        }
    }
    redirect(BASE_URL . '/admin/users/index.php');
}

$pageTitle = 'User Management';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Users</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> registered users</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users/create.php"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add User
    </a>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="<?= e($search) ?>"
                   placeholder="Name or email..."
                   class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div class="min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
            <select name="role" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All Roles</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['name'] ?>" <?= $role === $r['name'] ? 'selected' : '' ?>><?= ucfirst($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
        <?php if ($search || $role): ?>
        <a href="<?= BASE_URL ?>/admin/users/index.php" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center py-10 text-gray-400 text-sm">No users found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-brand-500 rounded-full flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white"><?= e($u['full_name']) ?></span>
                        </div>
                    </td>
                    <td class="text-sm text-gray-500"><?= e($u['email']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($u['phone'] ?? '—') ?></td>
                    <td>
                        <?php if ($u['id'] !== currentUser()['id']): ?>
                        <form method="POST" class="inline-flex">
                            <?= csrfField() ?>
                            <input type="hidden" name="change_role" value="1">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role_id" onchange="this.form.submit()"
                                    class="text-xs px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $u['role_id'] == $r['id'] ? 'selected' : '' ?>><?= ucfirst($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-gray-500 capitalize"><?= $u['role_name'] ?> (you)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="text-xs text-gray-500"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Never' ?></td>
                    <td class="text-xs text-gray-500"><?= formatDate($u['created_at'], 'M d, Y') ?></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $u['id'] ?>" class="text-xs text-gray-500 hover:underline">Edit</a>
                            <?php if ($u['id'] !== currentUser()['id']): ?>
                            <span class="text-gray-300">|</span>
                            <a href="?toggle=<?= $u['id'] ?>&csrf=<?= generateCSRF() ?>"
                               class="text-xs <?= $u['is_active'] ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700' ?> hover:underline"
                               data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 dark:border-gray-800">
        <p class="text-xs text-gray-500">Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $pagination['per_page'], $total) ?> of <?= $total ?></p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current']-1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Prev</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current']+1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
