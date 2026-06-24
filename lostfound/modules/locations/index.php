<?php
// ============================================================
// modules/locations/index.php
// C:/xampp/htdocs/lostfound/modules/locations/index.php
// Browser: http://localhost/lostfound/modules/locations/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin']);

$db     = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error','Invalid token.'); redirect(BASE_URL . '/modules/locations/index.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $floor    = trim($_POST['floor'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $errors[] = 'Location name is required.';
        } else {
            $db->prepare("INSERT INTO locations (name, building, floor, description) VALUES (?,?,?,?)")
               ->execute([$name, $building ?: null, $floor ?: null, $desc ?: null]);
            logActivity('create', 'locations', (int)$db->lastInsertId(), 'location', 'Created: ' . $name);
            setFlash('success', 'Location created.');
        }
    }

    if ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $floor    = trim($_POST['floor'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $active   = (int)($_POST['is_active'] ?? 1);
        if ($id && $name) {
            $db->prepare("UPDATE locations SET name=?, building=?, floor=?, description=?, is_active=? WHERE id=?")
               ->execute([$name, $building ?: null, $floor ?: null, $desc ?: null, $active, $id]);
            logActivity('update', 'locations', $id, 'location', 'Updated: ' . $name);
            setFlash('success', 'Location updated.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM locations WHERE id=?")->execute([$id]);
            logActivity('delete', 'locations', $id, 'location', 'Deleted location #' . $id);
            setFlash('success', 'Location deleted.');
        }
    }

    if (empty($errors)) redirect(BASE_URL . '/modules/locations/index.php');
}

$locations = $db->query("SELECT l.*,
    (SELECT COUNT(*) FROM lost_items WHERE location_id = l.id) AS lost_count,
    (SELECT COUNT(*) FROM found_items WHERE location_id = l.id) AS found_count
    FROM locations l ORDER BY l.name")->fetchAll();

$pageTitle = 'Locations';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Locations</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($locations) ?> locations</p>
    </div>
    <button data-modal-open="createModal"
            class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Location
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
                <th>Building</th>
                <th>Floor</th>
                <th>Description</th>
                <th>Lost</th>
                <th>Found</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($locations)): ?>
            <tr><td colspan="8" class="text-center py-10 text-gray-400 text-sm">No locations yet.</td></tr>
            <?php else: foreach ($locations as $loc): ?>
            <tr>
                <td class="font-medium text-gray-900 dark:text-white text-sm"><?= e($loc['name']) ?></td>
                <td class="text-sm text-gray-500"><?= e($loc['building'] ?? '—') ?></td>
                <td class="text-sm text-gray-500"><?= e($loc['floor'] ?? '—') ?></td>
                <td class="text-sm text-gray-500"><?= e($loc['description'] ?? '—') ?></td>
                <td class="text-sm text-gray-500"><?= $loc['lost_count'] ?></td>
                <td class="text-sm text-gray-500"><?= $loc['found_count'] ?></td>
                <td>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $loc['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $loc['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($loc)) ?>)"
                                class="text-xs text-gray-500 hover:underline">Edit</button>
                        <?php if ($loc['lost_count'] == 0 && $loc['found_count'] == 0): ?>
                        <span class="text-gray-300">|</span>
                        <form method="POST" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 hover:underline"
                                    data-confirm="Delete this location?">Delete</button>
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
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Add Location</h3>
            <button data-modal-close="createModal" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="e.g. Library">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Building</label>
                    <input type="text" name="building"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                           placeholder="Main Building">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Floor</label>
                    <input type="text" name="floor"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                           placeholder="2nd Floor">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                <input type="text" name="description"
                       class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
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
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Edit Location</h3>
            <button data-modal-close="editModal" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
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
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Building</label>
                    <input type="text" name="building" id="editBuilding"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Floor</label>
                    <input type="text" name="floor" id="editFloor"
                           class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
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
function openEditModal(loc) {
    document.getElementById('editId').value       = loc.id;
    document.getElementById('editName').value     = loc.name;
    document.getElementById('editBuilding').value = loc.building || '';
    document.getElementById('editFloor').value    = loc.floor || '';
    document.getElementById('editDesc').value     = loc.description || '';
    document.getElementById('editActive').value   = loc.is_active;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
