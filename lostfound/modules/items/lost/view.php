<?php
// ============================================================
// modules/items/lost/view.php
// C:/xampp/htdocs/lostfound/modules/items/lost/view.php
// Browser: http://localhost/lostfound/modules/items/lost/view.php?id=1
// ============================================================
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
startSecureSession();
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error', 'Invalid item.'); redirect(BASE_URL . '/modules/items/lost/index.php'); }

$stmt = $db->prepare("SELECT li.*, c.name AS category_name, l.name AS location_name, u.full_name AS reported_by_name
    FROM lost_items li
    LEFT JOIN categories c ON li.category_id = c.id
    LEFT JOIN locations  l ON li.location_id  = l.id
    LEFT JOIN users      u ON li.reported_by  = u.id
    WHERE li.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { setFlash('error', 'Item not found.'); redirect(BASE_URL . '/modules/items/lost/index.php'); }

// Potential matches
$matches = $db->prepare("SELECT im.*, fi.item_name AS found_name, fi.item_code AS found_code,
    fi.date_found, fi.photo AS found_photo, im.match_score, im.status AS match_status,
    c.name AS found_category, l.name AS found_location
    FROM item_matches im
    JOIN found_items fi ON im.found_item_id = fi.id
    LEFT JOIN categories c ON fi.category_id = c.id
    LEFT JOIN locations  l ON fi.location_id  = l.id
    WHERE im.lost_item_id = ?
    ORDER BY im.match_score DESC");
$matches->execute([$id]);
$matches = $matches->fetchAll();

$pageTitle = 'Lost Item — ' . $item['item_name'];
require_once __DIR__ . '/../../../layouts/header.php';
require_once __DIR__ . '/../../../layouts/sidebar.php';
?>

<div class="max-w-3xl">
    <a href="<?= BASE_URL ?>/modules/items/lost/index.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Lost Items
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-5">
        <!-- Header bar -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs font-mono text-gray-400"><?= e($item['item_code']) ?></p>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white"><?= e($item['item_name']) ?></h2>
            </div>
            <div class="flex items-center gap-3">
                <?= statusBadge($item['status']) ?>
                <?php if (isStaff()): ?>
                <a href="<?= BASE_URL ?>/modules/items/lost/edit.php?id=<?= $item['id'] ?>"
                   class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">Edit</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-0">
            <!-- Photo -->
            <div class="p-6 border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-800 flex items-start justify-center">
                <?php if ($item['photo']): ?>
                <img src="<?= getImageUrl($item['photo'], 'lost') ?>" class="w-40 h-40 object-cover rounded-xl border border-gray-200">
                <?php else: ?>
                <div class="w-40 h-40 bg-red-50 rounded-xl flex flex-col items-center justify-center text-red-200">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                    <p class="text-xs text-red-300 mt-2">No photo</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="p-6 md:col-span-2 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Category</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['category_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Location Lost</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['location_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Date Lost</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= formatDate($item['date_lost']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Time Lost</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= $item['time_lost'] ? date('h:i A', strtotime($item['time_lost'])) : '—' ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Owner</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['owner_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Contact</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['owner_contact']) ?></p>
                    </div>
                    <?php if ($item['owner_email']): ?>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-400 mb-0.5">Email</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['owner_email']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['description']): ?>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-400 mb-0.5">Description</p>
                        <p class="text-gray-700 dark:text-gray-300"><?= nl2br(e($item['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['notes']): ?>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-400 mb-0.5">Notes</p>
                        <p class="text-gray-700 dark:text-gray-300"><?= nl2br(e($item['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">
                    Reported <?= timeAgo($item['created_at']) ?> by <?= e($item['reported_by_name'] ?? 'Unknown') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Potential Matches -->
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Potential Matches</h3>
            <a href="<?= BASE_URL ?>/modules/matching/index.php" class="text-xs text-brand-500 hover:underline">Run matching</a>
        </div>
        <?php if (empty($matches)): ?>
        <p class="px-6 py-8 text-sm text-gray-400 text-center">No matches found yet. Run the matching engine to find potential found items.</p>
        <?php else: ?>
        <div class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php foreach ($matches as $m): ?>
            <div class="flex items-center gap-4 px-6 py-4">
                <?php if ($m['found_photo']): ?>
                <img src="<?= getImageUrl($m['found_photo'], 'found') ?>" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                <?php else: ?>
                <div class="w-12 h-12 bg-blue-50 rounded-lg flex-shrink-0"></div>
                <?php endif; ?>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white"><?= e($m['found_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= e($m['found_code']) ?> &middot; Found <?= formatDate($m['date_found']) ?></p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-center">
                        <p class="text-lg font-bold text-brand-500"><?= $m['match_score'] ?>%</p>
                        <p class="text-[10px] text-gray-400">match</p>
                    </div>
                    <?= statusBadge($m['match_status']) ?>
                    <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $m['found_item_id'] ?>"
                       class="text-xs text-brand-500 hover:underline">View</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../../layouts/footer.php'; ?>
