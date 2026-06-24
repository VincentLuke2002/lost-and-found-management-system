<?php
// ============================================================
// modules/matching/index.php
// C:/xampp/htdocs/lostfound/modules/matching/index.php
// ============================================================
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSecureSession();
requireRole(['admin','staff']);

$db = getDB();

// ---- Scoring function ----
function calculateMatchScore(array $lost, array $found): int {
    $score = 0;

    // Same category = 30 pts
    if (!empty($lost['category_id']) && !empty($found['category_id'])
        && (int)$lost['category_id'] === (int)$found['category_id']) {
        $score += 30;
    }

    // Same location = 20 pts
    if (!empty($lost['location_id']) && !empty($found['location_id'])
        && (int)$lost['location_id'] === (int)$found['location_id']) {
        $score += 20;
    }

    // Found date is on or after lost date = 10 pts
    if (!empty($found['date_found']) && !empty($lost['date_lost'])
        && $found['date_found'] >= $lost['date_lost']) {
        $score += 10;
    }

    // Item name word overlap = up to 25 pts
    $stopWords  = ['a','an','the','of','in','at','on','and','or','my','i'];
    $lostWords  = array_diff(array_filter(explode(' ', strtolower($lost['item_name']))), $stopWords);
    $foundWords = array_diff(array_filter(explode(' ', strtolower($found['item_name']))), $stopWords);
    if (count($lostWords) > 0) {
        $common = array_intersect($lostWords, $foundWords);
        $score += (int)(count($common) / count($lostWords) * 25);
    }

    // Description keyword overlap = up to 15 pts
    if (!empty($lost['description']) && !empty($found['description'])) {
        $lostDesc  = array_diff(array_filter(explode(' ', strtolower($lost['description']))), $stopWords);
        $foundDesc = array_diff(array_filter(explode(' ', strtolower($found['description']))), $stopWords);
        if (count($lostDesc) > 0) {
            $common2 = array_intersect($lostDesc, $foundDesc);
            $score  += (int)(count($common2) / count($lostDesc) * 15);
        }
    }

    return min(100, $score);
}

// ---- Handle POST: run engine ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_matching') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid token.');
        redirect(BASE_URL . '/modules/matching/index.php');
    }

    $lostItems  = $db->query("SELECT * FROM lost_items WHERE status = 'missing'")->fetchAll();
    $foundItems = $db->query("SELECT * FROM found_items WHERE status = 'available'")->fetchAll();

    $inserted = 0;
    foreach ($lostItems as $lost) {
        foreach ($foundItems as $found) {
            $score = calculateMatchScore($lost, $found);
            if ($score >= MATCH_THRESHOLD) {
                $exists = $db->prepare("SELECT id FROM item_matches WHERE lost_item_id=? AND found_item_id=?");
                $exists->execute([$lost['id'], $found['id']]);
                if (!$exists->fetch()) {
                    $db->prepare("INSERT INTO item_matches (lost_item_id, found_item_id, match_score, status) VALUES (?,?,?,'suggested')")
                       ->execute([$lost['id'], $found['id'], $score]);
                    $inserted++;

                    if (!empty($lost['reported_by'])) {
                        createNotification(
                            (int)$lost['reported_by'],
                            'item_matched',
                            'Potential Match Found',
                            'A found item may match your lost "' . $lost['item_name'] . '".',
                            (int)$lost['id'],
                            'lost_item'
                        );
                    }
                }
            }
        }
    }

    logActivity('run_matching', 'matching', null, null, "Engine run. {$inserted} new matches.");
    setFlash('success', "Matching complete — {$inserted} new potential match" . ($inserted !== 1 ? 'es' : '') . " found.");
    redirect(BASE_URL . '/modules/matching/index.php');
}

// ---- Handle POST: confirm/reject a match ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error','Invalid token.');
        redirect(BASE_URL . '/modules/matching/index.php');
    }
    $matchId = (int)($_POST['match_id'] ?? 0);
    $action  = $_POST['match_action'] ?? '';
    if ($matchId && in_array($action, ['confirmed','rejected'], true)) {
        $db->prepare("UPDATE item_matches SET status=?, matched_by=? WHERE id=?")
           ->execute([$action, currentUser()['id'], $matchId]);
        if ($action === 'confirmed') {
            $mRow = $db->prepare("SELECT lost_item_id FROM item_matches WHERE id=?");
            $mRow->execute([$matchId]);
            $mRow = $mRow->fetch();
            if ($mRow) {
                $db->prepare("UPDATE lost_items SET status='matched' WHERE id=?")
                   ->execute([$mRow['lost_item_id']]);
            }
        }
        setFlash('success', 'Match ' . $action . '.');
    }
    redirect(BASE_URL . '/modules/matching/index.php');
}

// ---- Load matches ----
$statusFilter = $_GET['status'] ?? '';
$where  = ['1=1'];
$params = [];
if ($statusFilter !== '') { $where[] = 'im.status = ?'; $params[] = $statusFilter; }
$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT im.*,
        li.item_name AS lost_name,  li.item_code AS lost_code,
        li.date_lost, li.owner_name, li.photo AS lost_photo,
        li.id AS lost_item_id,
        fi.item_name AS found_name, fi.item_code AS found_code,
        fi.date_found, fi.photo AS found_photo,
        fi.id AS found_item_id,
        cl.name AS lost_category,   cf.name AS found_category,
        ll.name AS lost_location,   lf.name AS found_location
    FROM item_matches im
    JOIN lost_items  li ON im.lost_item_id  = li.id
    JOIN found_items fi ON im.found_item_id = fi.id
    LEFT JOIN categories cl ON li.category_id = cl.id
    LEFT JOIN categories cf ON fi.category_id = cf.id
    LEFT JOIN locations  ll ON li.location_id  = ll.id
    LEFT JOIN locations  lf ON fi.location_id  = lf.id
    WHERE {$whereSQL}
    ORDER BY im.match_score DESC, im.created_at DESC
");
$stmt->execute($params);
$matches = $stmt->fetchAll();

// Stats
$stats = [];
foreach (['suggested','confirmed','rejected'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM item_matches WHERE status=?");
    $c->execute([$s]);
    $stats[$s] = (int)$c->fetchColumn();
}

$lostCount  = (int)$db->query("SELECT COUNT(*) FROM lost_items WHERE status='missing'")->fetchColumn();
$foundCount = (int)$db->query("SELECT COUNT(*) FROM found_items WHERE status='available'")->fetchColumn();

$pageTitle = 'Item Matching';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Item Matching</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            <?= $lostCount ?> missing lost item<?= $lostCount !== 1 ? 's' : '' ?> &middot;
            <?= $foundCount ?> available found item<?= $foundCount !== 1 ? 's' : '' ?>
        </p>
    </div>

    <!-- Run engine — POST form, no JS confirm needed -->
    <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="run_matching">
        <button type="submit"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
            Run Matching Engine
        </button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <?php
    $statCards = [
        ['label' => 'Suggested',  'key' => 'suggested',  'color' => 'text-yellow-600', 'bg' => 'bg-yellow-50'],
        ['label' => 'Confirmed',  'key' => 'confirmed',  'color' => 'text-green-600',  'bg' => 'bg-green-50'],
        ['label' => 'Dismissed',  'key' => 'rejected',   'color' => 'text-gray-500',   'bg' => 'bg-gray-50'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 flex items-center gap-3">
        <div class="w-9 h-9 <?= $sc['bg'] ?> rounded-lg flex items-center justify-center flex-shrink-0">
            <span class="text-sm font-bold <?= $sc['color'] ?>"><?= $stats[$sc['key']] ?></span>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400"><?= $sc['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter tabs -->
<div class="flex gap-2 mb-5">
    <?php foreach (['' => 'All', 'suggested' => 'Suggested', 'confirmed' => 'Confirmed', 'rejected' => 'Dismissed'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
              <?= $statusFilter === $val
                  ? 'bg-brand-500 text-white'
                  : 'bg-white dark:bg-gray-900 text-gray-600 border border-gray-200 dark:border-gray-700 hover:bg-gray-50' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Match cards -->
<?php if (empty($matches)): ?>
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-14 text-center">
    <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
        </svg>
    </div>
    <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">No matches yet</p>
    <p class="text-sm text-gray-400 mb-4">
        <?php if ($lostCount === 0 || $foundCount === 0): ?>
        You need at least one missing lost item and one available found item before matching can run.
        <?php else: ?>
        Click "Run Matching Engine" above to scan <?= $lostCount ?> lost item<?= $lostCount !== 1 ? 's' : '' ?> against <?= $foundCount ?> found item<?= $foundCount !== 1 ? 's' : '' ?>.
        <?php endif; ?>
    </p>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($matches as $m): ?>
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">

        <!-- Match header -->
        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <?= statusBadge($m['status']) ?>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Score:</span>
                    <span class="text-sm font-bold <?= $m['match_score'] >= 70 ? 'text-green-600' : ($m['match_score'] >= 40 ? 'text-yellow-600' : 'text-red-500') ?>">
                        <?= $m['match_score'] ?>%
                    </span>
                    <div class="w-20 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full <?= $m['match_score'] >= 70 ? 'bg-green-500' : ($m['match_score'] >= 40 ? 'bg-yellow-400' : 'bg-red-400') ?>"
                             style="width:<?= $m['match_score'] ?>%"></div>
                    </div>
                </div>
            </div>

            <?php if ($m['status'] === 'suggested'): ?>
            <form method="POST" class="flex gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                <button name="match_action" value="confirmed"
                        class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-medium rounded-lg transition-colors">
                    Confirm Match
                </button>
                <button name="match_action" value="rejected"
                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs font-medium rounded-lg transition-colors">
                    Dismiss
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Side-by-side items -->
        <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800">

            <!-- Lost item -->
            <div class="p-5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-red-400 mb-3">Lost Item</p>
                <div class="flex items-start gap-3">
                    <?php if ($m['lost_photo']): ?>
                    <img src="<?= getImageUrl($m['lost_photo'], 'lost') ?>" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                    <?php else: ?>
                    <div class="w-12 h-12 bg-red-50 dark:bg-red-900/20 rounded-lg flex-shrink-0 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= e($m['lost_name']) ?></p>
                        <p class="text-xs font-mono text-gray-400"><?= e($m['lost_code']) ?></p>
                        <p class="text-xs text-gray-500 mt-1">Lost: <?= formatDate($m['date_lost']) ?></p>
                        <?php if ($m['lost_category']): ?>
                        <p class="text-xs text-gray-400"><?= e($m['lost_category']) ?></p>
                        <?php endif; ?>
                        <?php if ($m['lost_location']): ?>
                        <p class="text-xs text-gray-400"><?= e($m['lost_location']) ?></p>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/modules/items/lost/view.php?id=<?= $m['lost_item_id'] ?>"
                           class="text-xs text-brand-500 hover:underline mt-1.5 inline-block">View item &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Found item -->
            <div class="p-5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-blue-400 mb-3">Found Item</p>
                <div class="flex items-start gap-3">
                    <?php if ($m['found_photo']): ?>
                    <img src="<?= getImageUrl($m['found_photo'], 'found') ?>" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                    <?php else: ?>
                    <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex-shrink-0 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= e($m['found_name']) ?></p>
                        <p class="text-xs font-mono text-gray-400"><?= e($m['found_code']) ?></p>
                        <p class="text-xs text-gray-500 mt-1">Found: <?= formatDate($m['date_found']) ?></p>
                        <?php if ($m['found_category']): ?>
                        <p class="text-xs text-gray-400"><?= e($m['found_category']) ?></p>
                        <?php endif; ?>
                        <?php if ($m['found_location']): ?>
                        <p class="text-xs text-gray-400"><?= e($m['found_location']) ?></p>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/modules/items/found/view.php?id=<?= $m['found_item_id'] ?>"
                           class="text-xs text-brand-500 hover:underline mt-1.5 inline-block">View item &rarr;</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
