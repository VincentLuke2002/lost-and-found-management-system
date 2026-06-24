<?php
// ============================================================
// modules/items/lost/edit.php
// C:/xampp/htdocs/lostfound/modules/items/lost/edit.php
// ============================================================
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/upload.php';
startSecureSession();
requireRole(['admin','staff']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid item.'); redirect(BASE_URL . '/modules/items/lost/index.php'); }

$item = $db->prepare("SELECT * FROM lost_items WHERE id = ?");
$item->execute([$id]);
$item = $item->fetch();
if (!$item) { setFlash('error','Item not found.'); redirect(BASE_URL . '/modules/items/lost/index.php'); }

$errors     = [];
$values     = $item;
$categories = getCategories();
$locations  = getLocations();
$statuses   = ['missing','matched','claimed','returned','archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $values = array_merge($values, [
            'item_name'     => trim($_POST['item_name'] ?? ''),
            'description'   => trim($_POST['description'] ?? ''),
            'category_id'   => (int)($_POST['category_id'] ?? 0),
            'location_id'   => (int)($_POST['location_id'] ?? 0),
            'date_lost'     => $_POST['date_lost'] ?? '',
            'time_lost'     => $_POST['time_lost'] ?? '',
            'owner_name'    => trim($_POST['owner_name'] ?? ''),
            'owner_contact' => trim($_POST['owner_contact'] ?? ''),
            'owner_email'   => trim($_POST['owner_email'] ?? ''),
            'status'        => $_POST['status'] ?? 'missing',
            'notes'         => trim($_POST['notes'] ?? ''),
        ]);

        if (empty($values['item_name']))     $errors[] = 'Item name is required.';
        if (empty($values['date_lost']))     $errors[] = 'Date lost is required.';
        if (empty($values['owner_name']))    $errors[] = 'Owner name is required.';
        if (empty($values['owner_contact'])) $errors[] = 'Contact number is required.';

        $photoFilename = $item['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadImage($_FILES['photo'], UPLOAD_LOST);
            if (!$upload['success']) {
                $errors[] = $upload['error'];
            } else {
                if ($photoFilename) deleteUpload($photoFilename, UPLOAD_LOST);
                $photoFilename = $upload['filename'];
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE lost_items SET
                item_name=?, description=?, category_id=?, location_id=?,
                date_lost=?, time_lost=?, owner_name=?, owner_contact=?,
                owner_email=?, photo=?, status=?, notes=?
                WHERE id=?");
            $stmt->execute([
                $values['item_name'], $values['description'],
                $values['category_id'] ?: null, $values['location_id'] ?: null,
                $values['date_lost'], $values['time_lost'] ?: null,
                $values['owner_name'], $values['owner_contact'],
                $values['owner_email'] ?: null, $photoFilename,
                $values['status'], $values['notes'], $id
            ]);
            logActivity('update', 'lost_items', $id, 'lost_item', 'Updated lost item: ' . $values['item_name']);
            setFlash('success', 'Lost item updated successfully.');
            redirect(BASE_URL . '/modules/items/lost/view.php?id=' . $id);
        }
    }
}

$pageTitle = 'Edit Lost Item';
require_once __DIR__ . '/../../../layouts/header.php';
require_once __DIR__ . '/../../../layouts/sidebar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/modules/items/lost/view.php?id=<?= $id ?>"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Item
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Edit Lost Item</h2>
                <p class="text-xs text-gray-400 font-mono"><?= e($item['item_code']) ?></p>
            </div>
        </div>

        <?php if ($errors): ?>
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-5">
            <?= csrfField() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Item Name <span class="text-red-500">*</span></label>
                    <input type="text" name="item_name" value="<?= e($values['item_name']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                    <select name="category_id" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $values['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Location Lost</label>
                    <select name="location_id" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Select location</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= $values['location_id'] == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date Lost <span class="text-red-500">*</span></label>
                    <input type="date" name="date_lost" value="<?= e($values['date_lost']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $values['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"><?= e($values['description']) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Owner Name <span class="text-red-500">*</span></label>
                    <input type="text" name="owner_name" value="<?= e($values['owner_name']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact Number <span class="text-red-500">*</span></label>
                    <input type="text" name="owner_contact" value="<?= e($values['owner_contact']) ?>" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Current Photo</label>
                    <?php if ($values['photo']): ?>
                    <img src="<?= getImageUrl($values['photo'], 'lost') ?>" class="w-20 h-20 rounded-lg object-cover border border-gray-200 mb-2">
                    <?php endif; ?>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           data-preview="photoPreview"
                           class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100">
                    <img id="photoPreview" src="#" class="hidden w-20 h-20 rounded-lg object-cover border border-gray-200 mt-2">
                    <p class="text-xs text-gray-400 mt-1">Leave blank to keep current photo.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"><?= e($values['notes']) ?></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="<?= BASE_URL ?>/modules/items/lost/view.php?id=<?= $id ?>"
                   class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../layouts/footer.php'; ?>
