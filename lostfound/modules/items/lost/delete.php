<?php
// ============================================================
// modules/items/lost/delete.php
// C:/xampp/htdocs/lostfound/modules/items/lost/delete.php
// ============================================================
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/upload.php';
startSecureSession();
requireRole(['admin','staff']);

$id   = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!$id || !verifyCSRF($csrf)) {
    setFlash('error', 'Invalid request.');
    redirect(BASE_URL . '/modules/items/lost/index.php');
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM lost_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Item not found.');
    redirect(BASE_URL . '/modules/items/lost/index.php');
}

// Delete photo if exists
if ($item['photo']) {
    deleteUpload($item['photo'], UPLOAD_LOST);
}

$db->prepare("DELETE FROM lost_items WHERE id = ?")->execute([$id]);
logActivity('delete', 'lost_items', $id, 'lost_item', 'Deleted lost item: ' . $item['item_name']);
setFlash('success', 'Lost item deleted successfully.');
redirect(BASE_URL . '/modules/items/lost/index.php');
