<?php
// ============================================================
// modules/items/found/index.php
// C:/xampp/htdocs/lostfound/modules/items/found/index.php
// Browser: http://localhost/lostfound/modules/items/found/index.php
// ============================================================
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
startSecureSession();
requireLogin();

$db = getDB();

$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(fi.item_name LIKE ? OR fi.description LIKE ? OR fi.found_by_name LIKE ? OR fi.item_code LIKE ?)';
    $s        = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if ($status !== '') {
    $where[]  = 'fi.status = ?';
    $params[] = $status;
}
if ($category !== '') {
    $where[]  = 'fi.category_id = ?';
    $params[] = $category;
}
if ($location !== '') {
    $where[]  = 'fi.location_id = ?';
    $params[] = $location;
}

$whereSQL = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM found_items fi WHERE {$whereSQL}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pagination = paginate($total, $page);

$stmt = $db->prepare("SELECT fi.*, c.name AS category_name, l.name AS location_name
    FROM found_items fi
    LEFT JOIN categories c ON fi.category_id = c.id
    LEFT JOIN locations  l ON fi.location_id  = l.id
    WHERE {$whereSQL}
    ORDER BY fi.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$items = $stmt->fetchAll();

$categories = getCategories();
$locations  = getLocations();

$pageTitle = 'Found Items';
require_once __DIR__ . '/../../../layouts/header.php';
require_once __DIR__ . '/../../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Found Items</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
    </div>
    <?php if (isStaff()): ?>
    <a href="<?= BASE_URL ?>/modules/items/found/create.php"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Record Found Item
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
    <form method="GET" action="" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Search</label>
            <input type="text" name="search" value="<?= e($search) ?>"
                   placeholder="Item name, found by, code..."
                   class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div class="min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All Statuses</option>
                <?php foreach (['available','claimed','returned','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Category</label>
            <select name="category" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Location</label>
            <select name="location" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= $location == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-900 dark:bg-white dark:text-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
        <?php if ($search || $status || $category || $location): ?>
        <a href="<?= BASE_URL ?>/modules/items/found/index.php" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Active filter pills -->
    <?php if ($search || $status || $category || $location): ?>
    <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
        <span class="text-xs text-gray-400">Active filters:</span>
        <?php if ($status): ?>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-brand-50 text-brand-600 text-xs rounded-full font-medium">
            Status: <?= ucfirst($status) ?>
            <a href="?search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&location=<?= urlencode($location) ?>" class="hover:text-brand-800 ml-0.5">&times;</a>
        </span>
        <?php endif; ?>
        <?php if ($search): ?>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-medium">
            Search: "<?= e($search) ?>"
            <a href="?status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>&location=<?= urlencode($location) ?>" class="hover:text-gray-800 ml-0.5">&times;</a>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Date Found</th>
                    <th>Location</th>
                    <th>Found By</th>
                    <th>Storage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" class="text-center py-12 text-sm">
                        <?php if ($search || $status || $category || $location): ?>
                        <p class="text-gray-500 font-medium">No items match your current filters.</p>
                        <p class="text-gray-400 mt-1">Status filter is set to: <strong><?= $status ? ucfirst($status) : 'All' ?></strong></p>
                        <a href="<?= BASE_URL ?>/modules/items/found/index.php" class="text-brand-500 hover:underline mt-2 inline-block">Clear all filters</a>
                        <?php else: ?>
                        <p class="text-gray-400">No found items recorded yet.</p>
                        <?php if (isStaff()): ?>
                        <a href="<?= BASE_URL ?>/modules/items/found/create.php" class="text-brand-500 hover:underline ml-1">Record one now.</a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: foreach ($items as $item): ?>
                <tr data-search="<?= e($item['item_name'] . ' ' . ($item['found_by_name'] ?? '') . ' ' . $item['item_code']) ?>">
                    <td>
                        <div class="flex items-center gap-3">
                            <?php if ($item['photo']): ?>
                            <img src="<?= getImageUrl($item['photo'], 'found') ?>" class="w-9 h-9 rounded-lg object-cover flex-shrink-0">
                            <?php else: ?>
                            <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/></svg>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm"><?= e($item['item_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= e($item['item_code']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="text-gray-500 text-sm"><?= e($item['category_name'] ?? '—') ?></td>
                    <td class="text-gray-500 text-sm"><?= formatDate($item['date_found']) ?></td>
                    <td class="text-gray-500 text-sm"><?= e($item['location_name'] ?? '—') ?></td>
                    <td class="text-gray-500 text-sm"><?= e($item['found_by_name'] ?? '—') ?></td>
                    <td class="text-gray-500 text-sm"><?= e($item['storage_location'] ?? '—') ?></td>
                    <td><?= statusBadge($item['status']) ?></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $item['id'] ?>" class="text-xs text-brand-500 hover:underline">View</a>
                            <?php if (isStaff()): ?>
                            <span class="text-gray-300">|</span>
                            <a href="<?= BASE_URL ?>/modules/items/found/edit.php?id=<?= $item['id'] ?>" class="text-xs text-gray-500 hover:underline">Edit</a>
                            <span class="text-gray-300">|</span>
                            <a href="<?= BASE_URL ?>/modules/items/found/delete.php?id=<?= $item['id'] ?>&csrf=<?= generateCSRF() ?>"
                               class="text-xs text-red-400 hover:text-red-600 hover:underline"
                               data-confirm="Delete this found item record?">Delete</a>
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
        <p class="text-xs text-gray-500">
            Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $pagination['per_page'], $total) ?> of <?= $total ?>
        </p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current'] - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
               class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Prev</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current'] + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
               class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../../layouts/footer.php'; ?>
