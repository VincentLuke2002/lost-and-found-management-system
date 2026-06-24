<?php
// ============================================================
// modules/claims/index.php
// C:/xampp/htdocs/lostfound/modules/claims/index.php
// Browser: http://localhost/lostfound/modules/claims/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireLogin();

$db     = getDB();
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

// Regular users only see their own claims
if (!isStaff()) {
    $where[]  = 'cl.claimant_id = ?';
    $params[] = currentUser()['id'];
}

if ($status !== '') {
    $where[]  = 'cl.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[]  = '(cl.claimant_name LIKE ? OR cl.claim_code LIKE ? OR fi.item_name LIKE ?)';
    $s        = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s]);
}

$whereSQL = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM claims cl LEFT JOIN found_items fi ON cl.found_item_id = fi.id WHERE {$whereSQL}");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $page);

$stmt = $db->prepare("SELECT cl.*, fi.item_name AS found_item_name, fi.item_code AS found_item_code,
    li.item_name AS lost_item_name, u.full_name AS reviewer_name
    FROM claims cl
    LEFT JOIN found_items fi ON cl.found_item_id = fi.id
    LEFT JOIN lost_items  li ON cl.lost_item_id  = li.id
    LEFT JOIN users       u  ON cl.reviewed_by   = u.id
    WHERE {$whereSQL}
    ORDER BY cl.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$claims = $stmt->fetchAll();

$pageTitle = 'Claims';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Claims</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/claims/create.php"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Submit Claim
    </a>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="<?= e($search) ?>"
                   placeholder="Claimant name, code, item..."
                   class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div class="min-w-[160px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All Statuses</option>
                <?php foreach (['pending','under_review','approved','rejected','completed'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
        <?php if ($search || $status): ?>
        <a href="<?= BASE_URL ?>/modules/claims/index.php" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Claim Code</th>
                    <th>Found Item</th>
                    <th>Claimant</th>
                    <th>Contact</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($claims)): ?>
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400 text-sm">
                        No claims found.
                        <a href="<?= BASE_URL ?>/modules/claims/create.php" class="text-brand-500 hover:underline ml-1">Submit one.</a>
                    </td>
                </tr>
                <?php else: foreach ($claims as $cl): ?>
                <tr>
                    <td class="font-mono text-xs text-gray-500"><?= e($cl['claim_code']) ?></td>
                    <td>
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= e($cl['found_item_name'] ?? '—') ?></p>
                        <p class="text-xs text-gray-400"><?= e($cl['found_item_code'] ?? '') ?></p>
                    </td>
                    <td class="text-sm text-gray-700 dark:text-gray-300"><?= e($cl['claimant_name']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($cl['claimant_contact']) ?></td>
                    <td class="text-sm text-gray-500"><?= timeAgo($cl['created_at']) ?></td>
                    <td><?= statusBadge($cl['status']) ?></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="<?= BASE_URL ?>/modules/claims/view.php?id=<?= $cl['id'] ?>" class="text-xs text-brand-500 hover:underline">View</a>
                            <?php if (isStaff() && in_array($cl['status'], ['pending','under_review'])): ?>
                            <span class="text-gray-300">|</span>
                            <a href="<?= BASE_URL ?>/modules/claims/review.php?id=<?= $cl['id'] ?>" class="text-xs text-gray-500 hover:underline">Review</a>
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
            <a href="?page=<?= $pagination['current']-1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Prev</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current']+1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>