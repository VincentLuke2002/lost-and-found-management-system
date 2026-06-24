<?php
// ============================================================
// modules/notifications/index.php
// C:/xampp/htdocs/lostfound/modules/notifications/index.php
// Browser: http://localhost/lostfound/modules/notifications/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireLogin();

$db   = getDB();
$user = currentUser();

// Mark all as read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    setFlash('success','All notifications marked as read.');
    redirect(BASE_URL . '/modules/notifications/index.php');
}

// Mark single as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$nid, $user['id']]);
}

$page       = max(1, (int)($_GET['page'] ?? 1));
$countStmt  = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
$countStmt->execute([$user['id']]);
$total      = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $page, 20);

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$unread = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$unread->execute([$user['id']]);
$unreadCount = (int)$unread->fetchColumn();

$typeIcons = [
    'new_claim'      => ['bg' => 'bg-blue-50', 'text' => 'text-blue-500',   'path' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z'],
    'claim_approved' => ['bg' => 'bg-green-50', 'text' => 'text-green-500', 'path' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'claim_rejected' => ['bg' => 'bg-red-50',   'text' => 'text-red-500',   'path' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'item_matched'   => ['bg' => 'bg-indigo-50','text' => 'text-indigo-500','path' => 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'],
    'item_returned'  => ['bg' => 'bg-emerald-50','text' => 'text-emerald-500','path' => 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3'],
    'system'         => ['bg' => 'bg-gray-50',  'text' => 'text-gray-500',  'path' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'],
];

$pageTitle = 'Notifications';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Notifications</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= $unreadCount ?> unread</p>
    </div>
    <?php if ($unreadCount > 0): ?>
    <a href="?mark_all=1" class="text-sm text-brand-500 hover:underline">Mark all as read</a>
    <?php endif; ?>
</div>

<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
    <?php if (empty($notifications)): ?>
    <div class="py-16 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
            </svg>
        </div>
        <p class="text-sm text-gray-500">No notifications yet.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50 dark:divide-gray-800">
        <?php foreach ($notifications as $notif):
            $icon = $typeIcons[$notif['type']] ?? $typeIcons['system'];
        ?>
        <div class="flex items-start gap-4 px-5 py-4 <?= !$notif['is_read'] ? 'bg-brand-50/30 dark:bg-brand-900/10' : '' ?> hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
            <div class="w-9 h-9 <?= $icon['bg'] ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 <?= $icon['text'] ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon['path'] ?>"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= e($notif['title']) ?>
                            <?php if (!$notif['is_read']): ?>
                            <span class="inline-block w-1.5 h-1.5 bg-brand-500 rounded-full ml-1 align-middle"></span>
                            <?php endif; ?>
                        </p>
                        <p class="text-sm text-gray-500 mt-0.5"><?= e($notif['message']) ?></p>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                    <a href="?read=<?= $notif['id'] ?>" class="text-xs text-gray-400 hover:text-gray-600 flex-shrink-0 mt-0.5">Mark read</a>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 mt-1.5"><?= timeAgo($notif['created_at']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 dark:border-gray-800">
        <p class="text-xs text-gray-500">Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $pagination['per_page'], $total) ?> of <?= $total ?></p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current']-1 ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Prev</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current']+1 ?>" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
