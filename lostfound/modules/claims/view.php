<?php
// ============================================================
// modules/claims/view.php
// C:/xampp/htdocs/lostfound/modules/claims/view.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireLogin();

$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

if (!$id) { setFlash('error','Invalid claim.'); redirect(BASE_URL . '/modules/claims/index.php'); }

$stmt = $db->prepare("SELECT cl.*,
    fi.item_name AS found_item_name, fi.item_code AS found_item_code, fi.photo AS found_photo,
    li.item_name AS lost_item_name,
    u.full_name AS reviewer_name
    FROM claims cl
    LEFT JOIN found_items fi ON cl.found_item_id = fi.id
    LEFT JOIN lost_items  li ON cl.lost_item_id  = li.id
    LEFT JOIN users       u  ON cl.reviewed_by   = u.id
    WHERE cl.id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) { setFlash('error','Claim not found.'); redirect(BASE_URL . '/modules/claims/index.php'); }

// Non-staff can only see their own claims
if (!isStaff() && $claim['claimant_id'] != $user['id']) {
    setFlash('error','Access denied.'); redirect(BASE_URL . '/modules/claims/index.php');
}

$pageTitle = 'Claim — ' . $claim['claim_code'];
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/modules/claims/index.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Claims
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-5">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs font-mono text-gray-400"><?= e($claim['claim_code']) ?></p>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Claim for: <?= e($claim['found_item_name'] ?? 'Unknown Item') ?>
                </h2>
            </div>
            <div class="flex items-center gap-3">
                <?= statusBadge($claim['status']) ?>
                <?php if (isStaff() && in_array($claim['status'], ['pending','under_review'])): ?>
                <a href="<?= BASE_URL ?>/modules/claims/review.php?id=<?= $claim['id'] ?>"
                   class="text-sm bg-brand-500 hover:bg-brand-600 text-white px-3 py-1.5 rounded-lg transition-colors">Review</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Found Item Reference -->
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Item Referenced</p>
                <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                    <?php if ($claim['found_photo']): ?>
                    <img src="<?= getImageUrl($claim['found_photo'], 'found') ?>" class="w-14 h-14 rounded-lg object-cover flex-shrink-0">
                    <?php else: ?>
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex-shrink-0"></div>
                    <?php endif; ?>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white"><?= e($claim['found_item_name'] ?? '—') ?></p>
                        <p class="text-xs text-gray-400 font-mono"><?= e($claim['found_item_code'] ?? '') ?></p>
                        <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $claim['found_item_id'] ?>"
                           class="text-xs text-brand-500 hover:underline mt-1 inline-block">View item</a>
                    </div>
                </div>
            </div>

            <!-- Claimant Info -->
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Claimant</p>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Name</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Contact</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_contact']) ?></p>
                    </div>
                    <?php if ($claim['claimant_email']): ?>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-400 mb-0.5">Email</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_email']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Proof -->
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Proof of Ownership</p>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                    <?= nl2br(e($claim['proof_of_ownership'])) ?>
                </p>
                <?php if ($claim['evidence_file']): ?>
                <div class="mt-3">
                    <p class="text-xs text-gray-400 mb-2">Evidence Photo</p>
                    <img src="<?= getImageUrl($claim['evidence_file'], 'evidence') ?>"
                         class="w-40 h-40 object-cover rounded-xl border border-gray-200">
                </div>
                <?php endif; ?>
            </div>

            <?php if ($claim['notes']): ?>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Notes</p>
                <p class="text-sm text-gray-700 dark:text-gray-300"><?= nl2br(e($claim['notes'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Review Result -->
            <?php if ($claim['reviewed_by']): ?>
            <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Review Result</p>
                <div class="flex items-start gap-3 p-4 rounded-xl <?= $claim['status'] === 'approved' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' ?>">
                    <div class="flex-1">
                        <p class="text-sm font-medium <?= $claim['status'] === 'approved' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' ?>">
                            <?= ucfirst($claim['status']) ?> by <?= e($claim['reviewer_name']) ?>
                        </p>
                        <?php if ($claim['review_notes']): ?>
                        <p class="text-sm mt-1 <?= $claim['status'] === 'approved' ? 'text-green-700' : 'text-red-700' ?>">
                            <?= nl2br(e($claim['review_notes'])) ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1"><?= timeAgo($claim['reviewed_at']) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-xs text-gray-400 border-t border-gray-100 dark:border-gray-800 pt-4">
                Submitted <?= timeAgo($claim['created_at']) ?>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>