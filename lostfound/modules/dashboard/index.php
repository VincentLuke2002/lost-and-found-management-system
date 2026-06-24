<?php
// ============================================================
// modules/dashboard/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireLogin();

$db = getDB();

// Stats
$stats = [];
$queries = [
    'total_lost'      => "SELECT COUNT(*) FROM lost_items",
    'total_found'     => "SELECT COUNT(*) FROM found_items",
    'pending_claims'  => "SELECT COUNT(*) FROM claims WHERE status = 'pending'",
    'approved_claims' => "SELECT COUNT(*) FROM claims WHERE status = 'approved'",
    'returned'        => "SELECT COUNT(*) FROM found_items WHERE status = 'returned'",
    'unclaimed'       => "SELECT COUNT(*) FROM found_items WHERE status = 'available'",
];
foreach ($queries as $key => $sql) {
    $stats[$key] = (int) $db->query($sql)->fetchColumn();
}

// Recent Lost Items
$recentLost = $db->query("
    SELECT li.*, c.name AS category_name
    FROM lost_items li
    LEFT JOIN categories c ON li.category_id = c.id
    ORDER BY li.created_at DESC LIMIT 5
")->fetchAll();

// Recent Activity
$recentActivity = $db->query("
    SELECT al.*, u.full_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
<?php
$cards = [
    ['label' => 'Lost Items',      'value' => $stats['total_lost'],      'color' => 'text-red-600',    'bg' => 'bg-red-50',    'icon' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z'],
    ['label' => 'Found Items',     'value' => $stats['total_found'],     'color' => 'text-blue-600',   'bg' => 'bg-blue-50',   'icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z'],
    ['label' => 'Pending Claims',  'value' => $stats['pending_claims'],  'color' => 'text-yellow-600', 'bg' => 'bg-yellow-50', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['label' => 'Approved Claims', 'value' => $stats['approved_claims'], 'color' => 'text-green-600',  'bg' => 'bg-green-50',  'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['label' => 'Returned',        'value' => $stats['returned'],        'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50', 'icon' => 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3'],
    ['label' => 'Unclaimed',       'value' => $stats['unclaimed'],       'color' => 'text-gray-600',   'bg' => 'bg-gray-50',   'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
];
foreach ($cards as $card): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs font-medium text-gray-500"><?= $card['label'] ?></p>
        <div class="w-8 h-8 <?= $card['bg'] ?> rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 <?= $card['color'] ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $card['icon'] ?>"/>
            </svg>
        </div>
    </div>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= number_format($card['value']) ?></p>
</div>
<?php endforeach; ?>
</div>

<!-- Quick Actions -->
<div class="flex flex-wrap gap-3 mb-8">
    <a href="<?= BASE_URL ?>/modules/items/lost/create.php"
       class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Report Lost Item
    </a>
    <?php if (isStaff()): ?>
    <a href="<?= BASE_URL ?>/modules/items/found/create.php"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Record Found Item
    </a>
    <a href="<?= BASE_URL ?>/modules/matching/index.php"
       class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
        Run Matching
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/modules/claims/create.php"
       class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
        Submit Claim
    </a>
</div>

<!-- Two column layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Recent Lost Items -->
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Lost Items</h3>
            <a href="<?= BASE_URL ?>/modules/items/lost/index.php" class="text-xs text-brand-500 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php if (empty($recentLost)): ?>
            <p class="px-5 py-8 text-sm text-gray-400 text-center">No lost items yet.</p>
            <?php else: foreach ($recentLost as $item): ?>
            <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                    <?php if ($item['photo']): ?>
                    <img src="<?= BASE_URL ?>/uploads/lost_items/<?= e($item['photo']) ?>"
                         class="w-8 h-8 object-cover"
                         onerror="this.style.display='none'">
                    <?php else: ?>
                    <svg class="w-4 h-4 text-red-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= e($item['item_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= e($item['category_name'] ?? 'Uncategorized') ?> &middot; <?= formatDate($item['date_lost']) ?></p>
                </div>
                <?= statusBadge($item['status']) ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php if (empty($recentActivity)): ?>
            <p class="px-5 py-8 text-sm text-gray-400 text-center">No activity yet.</p>
            <?php else: foreach ($recentActivity as $log): ?>
            <div class="flex items-start gap-3 px-5 py-3">
                <div class="w-7 h-7 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="text-[10px] font-semibold text-gray-500">
                        <?= strtoupper(substr($log['full_name'] ?? 'S', 0, 1)) ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium"><?= e($log['full_name'] ?? 'System') ?></span>
                        <span class="text-gray-500"> — <?= e(ucfirst(str_replace('_', ' ', $log['action']))) ?></span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($log['created_at']) ?></p>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
