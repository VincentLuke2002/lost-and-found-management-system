<?php
// ============================================================
// modules/claims/review.php
// C:/xampp/htdocs/lostfound/modules/claims/review.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin','staff']);

$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

if (!$id) { setFlash('error','Invalid claim.'); redirect(BASE_URL . '/modules/claims/index.php'); }

$stmt = $db->prepare("SELECT cl.*, fi.item_name AS found_item_name, fi.item_code AS found_item_code,
    fi.id AS found_id, fi.photo AS found_photo
    FROM claims cl
    LEFT JOIN found_items fi ON cl.found_item_id = fi.id
    WHERE cl.id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) { setFlash('error','Claim not found.'); redirect(BASE_URL . '/modules/claims/index.php'); }
if (!in_array($claim['status'], ['pending','under_review'])) {
    setFlash('error','This claim has already been reviewed.');
    redirect(BASE_URL . '/modules/claims/view.php?id=' . $id);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $decision     = $_POST['decision'] ?? '';
        $reviewNotes  = trim($_POST['review_notes'] ?? '');

        if (!in_array($decision, ['approve','reject'])) {
            $errors[] = 'Please select a decision.';
        }

        if (empty($errors)) {
            $newStatus     = $decision === 'approve' ? 'approved' : 'rejected';
            $itemStatus    = $decision === 'approve' ? 'claimed' : null;

            // Update claim
            $db->prepare("UPDATE claims SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
               ->execute([$newStatus, $user['id'], $reviewNotes ?: null, $id]);

            // Update found item status if approved
            if ($itemStatus) {
                $db->prepare("UPDATE found_items SET status=? WHERE id=?")
                   ->execute([$itemStatus, $claim['found_id']]);
            }

            // Notify claimant
            if ($claim['claimant_id']) {
                $notifType = $decision === 'approve' ? 'claim_approved' : 'claim_rejected';
                $notifTitle = $decision === 'approve' ? 'Claim Approved' : 'Claim Rejected';
                $notifMsg   = $decision === 'approve'
                    ? 'Your claim for "' . $claim['found_item_name'] . '" has been approved. Please visit the office to collect your item.'
                    : 'Your claim for "' . $claim['found_item_name'] . '" has been rejected. ' . $reviewNotes;
                createNotification($claim['claimant_id'], $notifType, $notifTitle, $notifMsg, $id, 'claim');
            }

            logActivity('review', 'claims', $id, 'claim', 'Claim ' . $newStatus . ': ' . $claim['claim_code']);
            setFlash('success', 'Claim has been ' . $newStatus . '.');
            redirect(BASE_URL . '/modules/claims/view.php?id=' . $id);
        }
    }
}

// Mark as under review automatically
if ($claim['status'] === 'pending') {
    $db->prepare("UPDATE claims SET status='under_review' WHERE id=?")->execute([$id]);
}

$pageTitle = 'Review Claim';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/modules/claims/view.php?id=<?= $id ?>"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Claim
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Review Claim</h2>
        <p class="text-xs text-gray-400 font-mono mb-6"><?= e($claim['claim_code']) ?></p>

        <!-- Claim Summary -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 mb-6 space-y-3">
            <div class="flex items-center gap-3">
                <?php if ($claim['found_photo']): ?>
                <img src="<?= getImageUrl($claim['found_photo'], 'found') ?>" class="w-12 h-12 rounded-lg object-cover">
                <?php endif; ?>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white"><?= e($claim['found_item_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= e($claim['found_item_code']) ?></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm pt-2 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-400">Claimant</p>
                    <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Contact</p>
                    <p class="font-medium text-gray-800 dark:text-gray-200"><?= e($claim['claimant_contact']) ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-400">Proof of Ownership</p>
                    <p class="text-gray-700 dark:text-gray-300 mt-1"><?= nl2br(e($claim['proof_of_ownership'])) ?></p>
                </div>
                <?php if ($claim['evidence_file']): ?>
                <div class="col-span-2">
                    <p class="text-xs text-gray-400 mb-1">Evidence</p>
                    <img src="<?= getImageUrl($claim['evidence_file'], 'evidence') ?>" class="w-32 h-32 object-cover rounded-lg border border-gray-200">
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errors): ?>
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Decision <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative flex items-center gap-3 p-4 border-2 rounded-xl cursor-pointer transition-colors peer-checked:border-green-500 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                        <input type="radio" name="decision" value="approve" class="peer sr-only" required>
                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0 peer-checked:border-green-500">
                            <div class="w-2.5 h-2.5 rounded-full bg-green-500 hidden peer-checked:block"></div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Approve</p>
                            <p class="text-xs text-gray-500">Item will be marked as claimed</p>
                        </div>
                    </label>
                    <label class="relative flex items-center gap-3 p-4 border-2 rounded-xl cursor-pointer transition-colors has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                        <input type="radio" name="decision" value="reject" class="peer sr-only">
                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                            <div class="w-2.5 h-2.5 rounded-full bg-red-500 hidden"></div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Reject</p>
                            <p class="text-xs text-gray-500">Item remains available</p>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Review Notes</label>
                <textarea name="review_notes" rows="3"
                          class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"
                          placeholder="Reason for approval or rejection (will be shown to claimant)..."></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Submit Decision
                </button>
                <a href="<?= BASE_URL ?>/modules/claims/view.php?id=<?= $id ?>"
                   class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>