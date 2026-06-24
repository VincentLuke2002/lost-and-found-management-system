<?php
// ============================================================
// modules/reports/index.php
// C:/xampp/htdocs/lostfound/modules/reports/index.php
// Browser: http://localhost/lostfound/modules/reports/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin','staff']);

$db = getDB();

// --- Date range filters ---
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // first day of current month
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');  // today
$type     = $_GET['type']      ?? 'overview';

// --- Overview stats ---
$overview = [
    'lost_total'     => (int)$db->query("SELECT COUNT(*) FROM lost_items")->fetchColumn(),
    'lost_missing'   => (int)$db->query("SELECT COUNT(*) FROM lost_items WHERE status='missing'")->fetchColumn(),
    'lost_returned'  => (int)$db->query("SELECT COUNT(*) FROM lost_items WHERE status='returned'")->fetchColumn(),
    'found_total'    => (int)$db->query("SELECT COUNT(*) FROM found_items")->fetchColumn(),
    'found_available'=> (int)$db->query("SELECT COUNT(*) FROM found_items WHERE status='available'")->fetchColumn(),
    'found_returned' => (int)$db->query("SELECT COUNT(*) FROM found_items WHERE status='returned'")->fetchColumn(),
    'claims_total'   => (int)$db->query("SELECT COUNT(*) FROM claims")->fetchColumn(),
    'claims_pending' => (int)$db->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn(),
    'claims_approved'=> (int)$db->query("SELECT COUNT(*) FROM claims WHERE status='approved'")->fetchColumn(),
    'claims_rejected'=> (int)$db->query("SELECT COUNT(*) FROM claims WHERE status='rejected'")->fetchColumn(),
];

// --- Lost items in date range ---
$lostItems = $db->prepare("
    SELECT li.*, c.name AS category_name, l.name AS location_name
    FROM lost_items li
    LEFT JOIN categories c ON li.category_id = c.id
    LEFT JOIN locations  l ON li.location_id  = l.id
    WHERE li.date_lost BETWEEN ? AND ?
    ORDER BY li.date_lost DESC
");
$lostItems->execute([$dateFrom, $dateTo]);
$lostItems = $lostItems->fetchAll();

// --- Found items in date range ---
$foundItems = $db->prepare("
    SELECT fi.*, c.name AS category_name, l.name AS location_name
    FROM found_items fi
    LEFT JOIN categories c ON fi.category_id = c.id
    LEFT JOIN locations  l ON fi.location_id  = l.id
    WHERE fi.date_found BETWEEN ? AND ?
    ORDER BY fi.date_found DESC
");
$foundItems->execute([$dateFrom, $dateTo]);
$foundItems = $foundItems->fetchAll();

// --- Claims in date range ---
$claims = $db->prepare("
    SELECT cl.*, fi.item_name AS found_item_name, fi.item_code AS found_item_code
    FROM claims cl
    LEFT JOIN found_items fi ON cl.found_item_id = fi.id
    WHERE DATE(cl.created_at) BETWEEN ? AND ?
    ORDER BY cl.created_at DESC
");
$claims->execute([$dateFrom, $dateTo]);
$claims = $claims->fetchAll();

// --- Category breakdown ---
$catBreakdown = $db->query("
    SELECT c.name,
        (SELECT COUNT(*) FROM lost_items  WHERE category_id = c.id) AS lost_count,
        (SELECT COUNT(*) FROM found_items WHERE category_id = c.id) AS found_count
    FROM categories c
    ORDER BY (lost_count + found_count) DESC
    LIMIT 10
")->fetchAll();

// --- Monthly summary (last 6 months) ---
$monthly = $db->query("
    SELECT
        DATE_FORMAT(date_lost, '%Y-%m') AS month,
        COUNT(*) AS lost_count
    FROM lost_items
    WHERE date_lost >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_lost, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

$monthlyFound = $db->query("
    SELECT
        DATE_FORMAT(date_found, '%Y-%m') AS month,
        COUNT(*) AS found_count
    FROM found_items
    WHERE date_found >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_found, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Handle export
$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    $reportType = $_GET['report'] ?? 'lost';
    require_once __DIR__ . '/export_csv.php';
    exit;
}

$pageTitle = 'Reports';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<!-- Page header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Reports</h2>
        <p class="text-sm text-gray-500 mt-0.5">System summary and data exports</p>
    </div>
</div>

<!-- Date filter -->
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                   class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                   class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-900 dark:bg-white dark:text-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
            Apply Filter
        </button>
        <a href="<?= BASE_URL ?>/modules/reports/index.php"
           class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">Reset</a>
    </form>
</div>

<!-- Overview cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php
    $ocards = [
        ['label'=>'Total Lost',      'value'=>$overview['lost_total'],      'sub'=>$overview['lost_missing']   . ' still missing',  'color'=>'text-red-600',   'bg'=>'bg-red-50'],
        ['label'=>'Total Found',     'value'=>$overview['found_total'],     'sub'=>$overview['found_available']. ' available',       'color'=>'text-blue-600',  'bg'=>'bg-blue-50'],
        ['label'=>'Total Claims',    'value'=>$overview['claims_total'],    'sub'=>$overview['claims_pending'] . ' pending',         'color'=>'text-yellow-600','bg'=>'bg-yellow-50'],
        ['label'=>'Items Returned',  'value'=>$overview['found_returned'],  'sub'=>$overview['claims_approved']. ' claims approved', 'color'=>'text-green-600', 'bg'=>'bg-green-50'],
    ];
    foreach ($ocards as $oc): ?>
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
        <p class="text-xs font-medium text-gray-500 mb-2"><?= $oc['label'] ?></p>
        <p class="text-2xl font-bold <?= $oc['color'] ?>"><?= number_format($oc['value']) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $oc['sub'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-5 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
    <?php foreach (['lost'=>'Lost Items','found'=>'Found Items','claims'=>'Claims','categories'=>'By Category','monthly'=>'Monthly'] as $tab=>$label): ?>
    <a href="?type=<?= $tab ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
       class="px-4 py-1.5 text-xs font-medium rounded-lg transition-colors <?= $type===$tab ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Export buttons -->
<div class="flex gap-2 mb-4">
    <a href="?export=csv&report=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export CSV
    </a>
    <button onclick="window.print()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
        </svg>
        Print
    </button>
</div>

<!-- Tab content -->
<?php if ($type === 'lost'): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Lost Items Report</h3>
        <span class="text-xs text-gray-400"><?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?> &middot; <?= count($lostItems) ?> records</span>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th><th>Item Name</th><th>Category</th>
                    <th>Date Lost</th><th>Location</th><th>Owner</th>
                    <th>Contact</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lostItems)): ?>
                <tr><td colspan="8" class="text-center py-8 text-gray-400 text-sm">No lost items in this date range.</td></tr>
                <?php else: foreach ($lostItems as $row): ?>
                <tr>
                    <td class="font-mono text-xs text-gray-400"><?= e($row['item_code']) ?></td>
                    <td class="font-medium text-sm text-gray-900 dark:text-white"><?= e($row['item_name']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['category_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-500"><?= formatDate($row['date_lost']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['location_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-700"><?= e($row['owner_name']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['owner_contact']) ?></td>
                    <td><?= statusBadge($row['status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($type === 'found'): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Found Items Report</h3>
        <span class="text-xs text-gray-400"><?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?> &middot; <?= count($foundItems) ?> records</span>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th><th>Item Name</th><th>Category</th>
                    <th>Date Found</th><th>Location</th><th>Found By</th>
                    <th>Storage</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($foundItems)): ?>
                <tr><td colspan="8" class="text-center py-8 text-gray-400 text-sm">No found items in this date range.</td></tr>
                <?php else: foreach ($foundItems as $row): ?>
                <tr>
                    <td class="font-mono text-xs text-gray-400"><?= e($row['item_code']) ?></td>
                    <td class="font-medium text-sm text-gray-900 dark:text-white"><?= e($row['item_name']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['category_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-500"><?= formatDate($row['date_found']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['location_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-700"><?= e($row['found_by_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['storage_location'] ?? '—') ?></td>
                    <td><?= statusBadge($row['status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($type === 'claims'): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Claims Report</h3>
        <span class="text-xs text-gray-400"><?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?> &middot; <?= count($claims) ?> records</span>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Claim Code</th><th>Item</th><th>Claimant</th>
                    <th>Contact</th><th>Submitted</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($claims)): ?>
                <tr><td colspan="6" class="text-center py-8 text-gray-400 text-sm">No claims in this date range.</td></tr>
                <?php else: foreach ($claims as $row): ?>
                <tr>
                    <td class="font-mono text-xs text-gray-400"><?= e($row['claim_code']) ?></td>
                    <td class="text-sm text-gray-900 dark:text-white"><?= e($row['found_item_name'] ?? '—') ?></td>
                    <td class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= e($row['claimant_name']) ?></td>
                    <td class="text-sm text-gray-500"><?= e($row['claimant_contact']) ?></td>
                    <td class="text-sm text-gray-500"><?= formatDate($row['created_at'], 'M d, Y') ?></td>
                    <td><?= statusBadge($row['status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($type === 'categories'): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Items by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Lost Items</th>
                    <th>Found Items</th>
                    <th>Total</th>
                    <th>Distribution</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotal = array_sum(array_map(fn($r) => $r['lost_count'] + $r['found_count'], $catBreakdown));
                foreach ($catBreakdown as $row):
                    $rowTotal = $row['lost_count'] + $row['found_count'];
                    $pct = $grandTotal > 0 ? round($rowTotal / $grandTotal * 100) : 0;
                ?>
                <tr>
                    <td class="font-medium text-sm text-gray-900 dark:text-white"><?= e($row['name']) ?></td>
                    <td class="text-sm text-red-600 font-medium"><?= $row['lost_count'] ?></td>
                    <td class="text-sm text-blue-600 font-medium"><?= $row['found_count'] ?></td>
                    <td class="text-sm font-semibold text-gray-900 dark:text-white"><?= $rowTotal ?></td>
                    <td class="w-40">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand-500 rounded-full" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-400 w-8 text-right"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($type === 'monthly'): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Monthly Summary — Last 6 Months</h3>
    </div>

    <?php
    // Build unified month list
    $allMonths = [];
    foreach ($monthly as $m)      $allMonths[$m['month']]['lost']  = $m['lost_count'];
    foreach ($monthlyFound as $m) $allMonths[$m['month']]['found'] = $m['found_count'];
    ksort($allMonths);
    ?>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Lost Items</th>
                    <th>Found Items</th>
                    <th>Difference</th>
                    <th>Visual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allMonths)): ?>
                <tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">No data yet.</td></tr>
                <?php else: foreach ($allMonths as $month => $counts):
                    $lost  = $counts['lost']  ?? 0;
                    $found = $counts['found'] ?? 0;
                    $diff  = $found - $lost;
                    $maxVal = max(array_map(fn($c) => max($c['lost'] ?? 0, $c['found'] ?? 0), $allMonths)) ?: 1;
                ?>
                <tr>
                    <td class="font-medium text-sm text-gray-900 dark:text-white">
                        <?= date('F Y', strtotime($month . '-01')) ?>
                    </td>
                    <td class="text-sm text-red-600 font-medium"><?= $lost ?></td>
                    <td class="text-sm text-blue-600 font-medium"><?= $found ?></td>
                    <td class="text-sm font-medium <?= $diff >= 0 ? 'text-green-600' : 'text-red-500' ?>">
                        <?= $diff >= 0 ? '+' : '' ?><?= $diff ?>
                    </td>
                    <td class="w-48">
                        <div class="space-y-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] text-red-400 w-8">Lost</span>
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-400 rounded-full" style="width:<?= round($lost/$maxVal*100) ?>%"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] text-blue-400 w-8">Found</span>
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-400 rounded-full" style="width:<?= round($found/$maxVal*100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals row -->
    <?php if (!empty($allMonths)):
        $totalLost  = array_sum(array_column($allMonths, 'lost'));
        $totalFound = array_sum(array_column($allMonths, 'found'));
    ?>
    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 flex items-center gap-8 text-sm">
        <span class="text-gray-500">6-month totals:</span>
        <span class="font-semibold text-red-600"><?= $totalLost ?> lost</span>
        <span class="font-semibold text-blue-600"><?= $totalFound ?> found</span>
        <span class="font-semibold <?= ($totalFound - $totalLost) >= 0 ? 'text-green-600' : 'text-red-500' ?>">
            <?= $totalFound - $totalLost >= 0 ? '+' : '' ?><?= $totalFound - $totalLost ?> net
        </span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Print styles -->
<style>
@media print {
    aside, header, .flex.gap-2.mb-4, form, .flex.gap-1.mb-5 { display: none !important; }
    body { background: white !important; }
    .rounded-xl { border-radius: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
