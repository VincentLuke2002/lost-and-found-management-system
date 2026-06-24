<?php
// ============================================================
// modules/claims/create.php
// C:/xampp/htdocs/lostfound/modules/claims/create.php
// Browser: http://localhost/lostfound/modules/claims/create.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/upload.php';
startSecureSession();
requireLogin();

$db       = getDB();
$errors   = [];
$values   = [];
$foundId  = (int)($_GET['found_id'] ?? 0);
$user     = currentUser();

// Pre-load found item if coming from view page
$foundItem = null;
if ($foundId) {
    $stmt = $db->prepare("SELECT * FROM found_items WHERE id = ? AND status = 'available'");
    $stmt->execute([$foundId]);
    $foundItem = $stmt->fetch();
}

// All available found items for dropdown
$availableItems = $db->query("SELECT id, item_code, item_name FROM found_items WHERE status = 'available' ORDER BY item_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $values = [
            'found_item_id'    => (int)($_POST['found_item_id'] ?? 0),
            'claimant_name'    => trim($_POST['claimant_name'] ?? ''),
            'claimant_contact' => trim($_POST['claimant_contact'] ?? ''),
            'claimant_email'   => trim($_POST['claimant_email'] ?? ''),
            'proof_of_ownership' => trim($_POST['proof_of_ownership'] ?? ''),
            'notes'            => trim($_POST['notes'] ?? ''),
        ];

        if (!$values['found_item_id'])         $errors[] = 'Please select a found item.';
        if (empty($values['claimant_name']))    $errors[] = 'Your name is required.';
        if (empty($values['claimant_contact'])) $errors[] = 'Contact number is required.';
        if (empty($values['proof_of_ownership'])) $errors[] = 'Proof of ownership description is required.';

        $evidenceFile = null;
        if (!empty($_FILES['evidence_file']['name'])) {
            $upload = uploadImage($_FILES['evidence_file'], UPLOAD_EVIDENCE);
            if (!$upload['success']) {
                $errors[] = $upload['error'];
            } else {
                $evidenceFile = $upload['filename'];
            }
        }

        if (empty($errors)) {
            // Check item is still available
            $check = $db->prepare("SELECT id FROM found_items WHERE id = ? AND status = 'available'");
            $check->execute([$values['found_item_id']]);
            if (!$check->fetch()) {
                $errors[] = 'This item is no longer available for claims.';
            } else {
                $code = generateCode('CL');
                $db->prepare("INSERT INTO claims
                    (claim_code, found_item_id, claimant_id, claimant_name, claimant_contact,
                     claimant_email, proof_of_ownership, evidence_file, notes, status)
                    VALUES (?,?,?,?,?,?,?,?,?,'pending')")
                ->execute([
                    $code,
                    $values['found_item_id'],
                    $user['id'],
                    $values['claimant_name'],
                    $values['claimant_contact'],
                    $values['claimant_email'] ?: null,
                    $values['proof_of_ownership'],
                    $evidenceFile,
                    $values['notes'],
                ]);

                // Notify admins
                $admins = $db->query("SELECT id FROM users WHERE role_id IN (1,2)")->fetchAll();
                foreach ($admins as $admin) {
                    createNotification($admin['id'], 'new_claim', 'New Claim Submitted',
                        $values['claimant_name'] . ' submitted a claim. Code: ' . $code,
                        (int)$db->lastInsertId(), 'claim');
                }

                logActivity('create', 'claims', (int)$db->lastInsertId(), 'claim', 'Submitted claim: ' . $code);
                setFlash('success', 'Claim submitted successfully. Code: ' . $code);
                redirect(BASE_URL . '/modules/claims/index.php');
            }
        }
    }
}

$pageTitle = 'Submit Claim';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/modules/claims/index.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Claims
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Submit an Ownership Claim</h2>
        <p class="text-sm text-gray-500 mb-6">Provide details to prove ownership of a found item.</p>

        <?php if ($errors): ?>
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <?= csrfField() ?>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold uppercase tracking-wider text-gray-400 pb-2 border-b border-gray-100 dark:border-gray-800 w-full">Item Being Claimed</legend>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Found Item <span class="text-red-500">*</span></label>
                    <select name="found_item_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Select a found item</option>
                        <?php foreach ($availableItems as $fi): ?>
                        <option value="<?= $fi['id'] ?>"
                            <?= ($values['found_item_id'] ?? $foundId) == $fi['id'] ? 'selected' : '' ?>>
                            [<?= e($fi['item_code']) ?>] <?= e($fi['item_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($availableItems)): ?>
                    <p class="text-xs text-yellow-600 mt-1">No items currently available for claims.</p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold uppercase tracking-wider text-gray-400 pb-2 border-b border-gray-100 dark:border-gray-800 w-full">Your Information</legend>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="claimant_name" value="<?= e($values['claimant_name'] ?? $user['name']) ?>" required
                               class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact Number <span class="text-red-500">*</span></label>
                        <input type="text" name="claimant_contact" value="<?= e($values['claimant_contact'] ?? '') ?>" required
                               class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                               placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="email" name="claimant_email" value="<?= e($values['claimant_email'] ?? $user['email']) ?>"
                               class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold uppercase tracking-wider text-gray-400 pb-2 border-b border-gray-100 dark:border-gray-800 w-full">Proof of Ownership</legend>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description <span class="text-red-500">*</span></label>
                    <textarea name="proof_of_ownership" rows="4" required
                              class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"
                              placeholder="Describe specific details only the owner would know — serial number, contents, unique markings, where you last had it..."><?= e($values['proof_of_ownership'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Evidence Photo <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div class="flex items-start gap-4">
                        <img id="evidencePreview" src="#" class="hidden w-20 h-20 rounded-lg object-cover border border-gray-200">
                        <div class="flex-1">
                            <input type="file" name="evidence_file" accept="image/jpeg,image/png,image/webp"
                                   data-preview="evidencePreview"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100">
                            <p class="text-xs text-gray-400 mt-1">Receipt, photo with item, ID — max 5MB</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Additional Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"
                              placeholder="Any other information..."><?= e($values['notes'] ?? '') ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Submit Claim
                </button>
                <a href="<?= BASE_URL ?>/modules/claims/index.php"
                   class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>