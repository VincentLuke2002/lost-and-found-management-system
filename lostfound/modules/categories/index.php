<?php
// ============================================================
// modules/categories/index.php
// C:/xampp/htdocs/lostfound/modules/categories/index.php
// Browser: http://localhost/lostfound/modules/categories/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin']);

$db     = getDB();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid token.'); redirect(BASE_URL . '/modules/categories/index.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $errors[] = 'Category name is required.';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            try {
                $db->prepare("INSERT INTO categories (name, slug, description) VALUES (?,?,?)")
                   ->execute([$name, $slug, $desc ?: null]);
                logActivity('create', 'categories', (int)$db->lastInsertId(), 'category', 'Created: ' . $name);
                setFlash('success', 'Category created.');
            } catch (Exception $e) {
                $errors[] = 'Category name already exists.';
            }
        }
    }

    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $active = (int)($_POST['is_active'] ?? 1);
        if ($id && $name) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $db->prepare("UPDATE categories SET name=?, slug=?, description=?, is_active=? WHERE id=?")
               ->execute([$name, $slug, $desc ?: null, $active, $id]);
            logActivity('update', 'categories', $id, 'category', 'Updated: ' . $name);
            setFlash('success', 'Category updated.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            logActivity('delete', 'categories', $id, 'category', 'Deleted category #' . $id);
            setFlash('success', 'Category deleted.');
        }
    }

    if (empty($errors)) redirect(BASE_URL . '/modules/categories/index.php');
}

$categories = $db->query("SELECT c.*, 
    (SELECT COUNT(*) FROM lost_items WHERE category_id = c.id) AS lost_count,
    (SELECT COUNT(*) FROM found_items WHERE category_id = c.id) AS found_count
    FROM categories c ORDER BY c.name")->fetchAll();

$pageTitle = 'Categories';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Categories</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($categories) ?> categories</p>
    </div>
    <button data-modal-open="createModal"
            class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Category
    </button>
</div>

<?php if ($errors): ?>
<div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
    <?php foreach ($errors as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Lost Items</th>
                <th>Found Items</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
            <tr><td colspan="7" class="text-center py-10 text-gray-400 text-sm">No categories yet.</td></tr>
            <?php else: foreach ($categories as $cat): ?>
            <tr>
                <td class="font-medium text-gray-900 dark:text-white text-sm"><?= e($cat['name']) ?></td>
                <td class="font-mono text-xs text-gray-400"><?= e($cat['slug']) ?></td>
                <td class="text-sm text-gray-500"><?= e($cat['description'] ?? '—') ?></td>
                <td class="text-sm text-gray-500"><?= $cat['lost_count'] ?></td>
                <td class="text-sm text-gray-500"><?= $cat['found_count'] ?></td>
                <td>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cat['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                class="text-xs text-gray-500 hover:underline">Edit</button>
                        <?php if ($cat['lost_count'] == 0 && $cat['found_count'] == 0): ?>
                        <span class="text-gray-300">|</span>
                        <form method="POST" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 hover:underline"
                                    data-confirm="Delete this category?">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" data-modal-backdrop>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Add Category</h3>
            <button data-modal-close="createModal" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="e.g. Electronics">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                <input type="text" name="description"
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="Optional description">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg">Create</button>
                <button type="button" data-modal-close="createModal" class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" data-modal-backdrop>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Edit Category</h3>
            <button data-modal-close="editModal" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="editName" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                <input type="text" name="description" id="editDesc"
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                <select name="is_active" id="editActive"
                        class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg">Save</button>
                <button type="button" data-modal-close="editModal" class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(cat) {
    document.getElementById('editId').value     = cat.id;
    document.getElementById('editName').value   = cat.name;
    document.getElementById('editDesc').value   = cat.description || '';
    document.getElementById('editActive').value = cat.is_active;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>