<?php
// ============================================================
// modules/items/found/view.php
// C:/xampp/htdocs/lostfound/modules/items/found/view.php
// Browser: http://localhost/lostfound/modules/items/found/view.php?id=1
// ============================================================
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
startSecureSession();
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid item.'); redirect(BASE_URL . '/modules/items/found/index.php'); }

$stmt = $db->prepare("SELECT fi.*, c.name AS category_name, l.name AS location_name, u.full_name AS recorded_by_name
    FROM found_items fi
    LEFT JOIN categories c ON fi.category_id = c.id
    LEFT JOIN locations  l ON fi.location_id  = l.id
    LEFT JOIN users      u ON fi.recorded_by  = u.id
    WHERE fi.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { setFlash('error','Item not found.'); redirect(BASE_URL . '/modules/items/found/index.php'); }

// Claims for this item
$claims = $db->prepare("SELECT cl.*, u.full_name AS claimant_user_name
    FROM claims cl
    LEFT JOIN users u ON cl.claimant_id = u.id
    WHERE cl.found_item_id = ?
    ORDER BY cl.created_at DESC");
$claims->execute([$id]);
$claims = $claims->fetchAll();

$pageTitle = 'Found Item — ' . $item['item_name'];
require_once __DIR__ . '/../../../layouts/header.php';
require_once __DIR__ . '/../../../layouts/sidebar.php';
?>

<div class="max-w-3xl">
    <a href="<?= BASE_URL ?>/modules/items/found/index.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Found Items
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-5">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs font-mono text-gray-400"><?= e($item['item_code']) ?></p>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white"><?= e($item['item_name']) ?></h2>
            </div>
            <div class="flex items-center gap-3">
                <?= statusBadge($item['status']) ?>
                <?php if (isStaff()): ?>
                <a href="<?= BASE_URL ?>/modules/items/found/edit.php?id=<?= $item['id'] ?>"
                   class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">Edit</a>
                <?php endif; ?>
                <?php if ($item['status'] === 'available'): ?>
                <a href="<?= BASE_URL ?>/modules/claims/create.php?found_id=<?= $item['id'] ?>"
                   class="text-sm bg-brand-500 hover:bg-brand-600 text-white px-3 py-1.5 rounded-lg transition-colors">Submit Claim</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-0">
            <div class="p-6 border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-800 flex items-start justify-center">
                <?php if ($item['photo']): ?>
                <img src="<?= getImageUrl($item['photo'], 'found') ?>" class="w-40 h-40 object-cover rounded-xl border border-gray-200">
                <?php else: ?>
                <div class="w-40 h-40 bg-blue-50 rounded-xl flex flex-col items-center justify-center text-blue-200">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/></svg>
                    <p class="text-xs text-blue-300 mt-2">No photo</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-6 md:col-span-2 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Category</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['category_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Location Found</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['location_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Date Found</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= formatDate($item['date_found']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Time Found</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= $item['time_found'] ? date('h:i A', strtotime($item['time_found'])) : '—' ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Found By</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['found_by_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Storage Location</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($item['storage_location'] ?? '—') ?></p>
                    </div>
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
                    Recorded <?= timeAgo($item['created_at']) ?> by <?= e($item['recorded_by_name'] ?? 'Unknown') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Claims -->
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Claims (<?= count($claims) ?>)</h3>
            <?php if ($item['status'] === 'available'): ?>
            <a href="<?= BASE_URL ?>/modules/claims/create.php?found_id=<?= $item['id'] ?>"
               class="text-xs text-brand-500 hover:underline">Submit claim</a>
            <?php endif; ?>
        </div>
        <?php if (empty($claims)): ?>
        <p class="px-6 py-8 text-sm text-gray-400 text-center">No claims submitted for this item yet.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Claim Code</th>
                        <th>Claimant</th>
                        <th>Contact</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td class="font-mono text-xs text-gray-500"><?= e($claim['claim_code']) ?></td>
                        <td class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_name']) ?></td>
                        <td class="text-sm text-gray-500"><?= e($claim['claimant_contact']) ?></td>
                        <td class="text-sm text-gray-500"><?= timeAgo($claim['created_at']) ?></td>
                        <td><?= statusBadge($claim['status']) ?></td>
                        <td><a href="<?= BASE_URL ?>/modules/claims/view.php?id=<?= $claim['id'] ?>" class="text-xs text-brand-500 hover:underline">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../../layouts/footer.php'; ?>