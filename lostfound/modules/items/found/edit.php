<?php
// ============================================================
// modules/items/found/edit.php
// C:/xampp/htdocs/lostfound/modules/items/found/edit.php
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
if (!$id) { setFlash('error','Invalid item.'); redirect(BASE_URL . '/modules/items/found/index.php'); }

$stmt = $db->prepare("SELECT * FROM found_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { setFlash('error','Item not found.'); redirect(BASE_URL . '/modules/items/found/index.php'); }

$errors     = [];
$values     = $item;
$categories = getCategories();
$locations  = getLocations();
$statuses   = ['available','claimed','returned','archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $values = array_merge($values, [
            'item_name'        => trim($_POST['item_name'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'category_id'      => (int)($_POST['category_id'] ?? 0),
            'location_id'      => (int)($_POST['location_id'] ?? 0),
            'date_found'       => $_POST['date_found'] ?? '',
            'time_found'       => $_POST['time_found'] ?? '',
            'found_by_name'    => trim($_POST['found_by_name'] ?? ''),
            'storage_location' => trim($_POST['storage_location'] ?? ''),
            'status'           => $_POST['status'] ?? 'available',
            'notes'            => trim($_POST['notes'] ?? ''),
        ]);

        if (empty($values['item_name']))  $errors[] = 'Item name is required.';
        if (empty($values['date_found'])) $errors[] = 'Date found is required.';

        $photoFilename = $item['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadImage($_FILES['photo'], UPLOAD_FOUND);
            if (!$upload['success']) {
                $errors[] = $upload['error'];
            } else {
                if ($photoFilename) deleteUpload($photoFilename, UPLOAD_FOUND);
                $photoFilename = $upload['filename'];
            }
        }

        if (empty($errors)) {
            $db->prepare("UPDATE found_items SET
                item_name=?, description=?, category_id=?, location_id=?,
                date_found=?, time_found=?, found_by_name=?, storage_location=?,
                photo=?, status=?, notes=? WHERE id=?")
            ->execute([
                $values['item_name'], $values['description'],
                $values['category_id'] ?: null, $values['location_id'] ?: null,
                $values['date_found'], $values['time_found'] ?: null,
                $values['found_by_name'] ?: null, $values['storage_location'] ?: null,
                $photoFilename, $values['status'], $values['notes'], $id
            ]);
            logActivity('update', 'found_items', $id, 'found_item', 'Updated found item: ' . $values['item_name']);
            setFlash('success', 'Found item updated.');
            redirect(BASE_URL . '/modules/items/found/view.php?id=' . $id);
        }
    }
}

$pageTitle = 'Edit Found Item';
require_once __DIR__ . '/../../../layouts/header.php';
require_once __DIR__ . '/../../../layouts/sidebar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $id ?>"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Item
    </a>

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Edit Found Item</h2>
            <p class="text-xs text-gray-400 font-mono"><?= e($item['item_code']) ?></p>
        </div>

        <?php if ($errors): ?>
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Location Found</label>
                    <select name="location_id" class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Select location</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= $values['location_id'] == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date Found <span class="text-red-500">*</span></label>
                    <input type="date" name="date_found" value="<?= e($values['date_found']) ?>" required
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Found By</label>
                    <input type="text" name="found_by_name" value="<?= e($values['found_by_name'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Storage Location</label>
                    <input type="text" name="storage_location" value="<?= e($values['storage_location'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"><?= e($values['description']) ?></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Photo</label>
                    <?php if ($values['photo']): ?>
                    <img src="<?= getImageUrl($values['photo'], 'found') ?>" class="w-20 h-20 rounded-lg object-cover border border-gray-200 mb-2">
                    <?php endif; ?>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photoPreview"
                           class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-600">
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
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors">Save Changes</button>
                <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $id ?>"
                   class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../layouts/footer.php'; ?>