<?php
// ============================================================
// modules/items/found/delete.php
// C:/xampp/htdocs/lostfound/modules/items/found/delete.php
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
    redirect(BASE_URL . '/modules/items/found/index.php');
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM found_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Item not found.');
    redirect(BASE_URL . '/modules/items/found/index.php');
}

if ($item['photo']) {
    deleteUpload($item['photo'], UPLOAD_FOUND);
}

$db->prepare("DELETE FROM found_items WHERE id = ?")->execute([$id]);
logActivity('delete', 'found_items', $id, 'found_item', 'Deleted found item: ' . $item['item_name']);
setFlash('success', 'Found item deleted successfully.');
redirect(BASE_URL . '/modules/items/found/index.php');