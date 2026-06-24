<?php
// ============================================================
// modules/reports/export_csv.php
// C:/xampp/htdocs/lostfound/modules/reports/export_csv.php
// Called internally from index.php — not accessed directly
// ============================================================

$reportType = $_GET['report'] ?? 'lost';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to']   ?? date('Y-m-d');

$filename = 'report_' . $reportType . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($out, "\xEF\xBB\xBF");

if ($reportType === 'lost') {
    fputcsv($out, ['Code','Item Name','Category','Date Lost','Location','Owner Name','Contact','Email','Status','Description','Reported']);

    $rows = $db->prepare("
        SELECT li.item_code, li.item_name, c.name AS cat, li.date_lost,
               l.name AS loc, li.owner_name, li.owner_contact, li.owner_email,
               li.status, li.description, li.created_at
        FROM lost_items li
        LEFT JOIN categories c ON li.category_id = c.id
        LEFT JOIN locations  l ON li.location_id  = l.id
        WHERE li.date_lost BETWEEN ? AND ?
        ORDER BY li.date_lost DESC
    ");
    $rows->execute([$dateFrom, $dateTo]);
    foreach ($rows->fetchAll() as $row) {
        fputcsv($out, [
            $row['item_code'],
            $row['item_name'],
            $row['cat'] ?? '',
            $row['date_lost'],
            $row['loc'] ?? '',
            $row['owner_name'],
            $row['owner_contact'],
            $row['owner_email'] ?? '',
            $row['status'],
            $row['description'] ?? '',
            $row['created_at'],
        ]);
    }

} elseif ($reportType === 'found') {
    fputcsv($out, ['Code','Item Name','Category','Date Found','Location','Found By','Storage Location','Status','Description','Recorded']);

    $rows = $db->prepare("
        SELECT fi.item_code, fi.item_name, c.name AS cat, fi.date_found,
               l.name AS loc, fi.found_by_name, fi.storage_location,
               fi.status, fi.description, fi.created_at
        FROM found_items fi
        LEFT JOIN categories c ON fi.category_id = c.id
        LEFT JOIN locations  l ON fi.location_id  = l.id
        WHERE fi.date_found BETWEEN ? AND ?
        ORDER BY fi.date_found DESC
    ");
    $rows->execute([$dateFrom, $dateTo]);
    foreach ($rows->fetchAll() as $row) {
        fputcsv($out, [
            $row['item_code'],
            $row['item_name'],
            $row['cat'] ?? '',
            $row['date_found'],
            $row['loc'] ?? '',
            $row['found_by_name'] ?? '',
            $row['storage_location'] ?? '',
            $row['status'],
            $row['description'] ?? '',
            $row['created_at'],
        ]);
    }

} elseif ($reportType === 'claims') {
    fputcsv($out, ['Claim Code','Found Item','Item Code','Claimant','Contact','Email','Status','Submitted','Reviewed By','Review Notes']);

    $rows = $db->prepare("
        SELECT cl.claim_code, fi.item_name, fi.item_code,
               cl.claimant_name, cl.claimant_contact, cl.claimant_email,
               cl.status, cl.created_at, u.full_name AS reviewer, cl.review_notes
        FROM claims cl
        LEFT JOIN found_items fi ON cl.found_item_id = fi.id
        LEFT JOIN users       u  ON cl.reviewed_by   = u.id
        WHERE DATE(cl.created_at) BETWEEN ? AND ?
        ORDER BY cl.created_at DESC
    ");
    $rows->execute([$dateFrom, $dateTo]);
    foreach ($rows->fetchAll() as $row) {
        fputcsv($out, [
            $row['claim_code'],
            $row['item_name'] ?? '',
            $row['item_code'] ?? '',
            $row['claimant_name'],
            $row['claimant_contact'],
            $row['claimant_email'] ?? '',
            $row['status'],
            $row['created_at'],
            $row['reviewer'] ?? '',
            $row['review_notes'] ?? '',
        ]);
    }

} elseif ($reportType === 'categories') {
    fputcsv($out, ['Category','Lost Items','Found Items','Total']);

    $rows = $db->query("
        SELECT c.name,
            (SELECT COUNT(*) FROM lost_items  WHERE category_id = c.id) AS lost_count,
            (SELECT COUNT(*) FROM found_items WHERE category_id = c.id) AS found_count
        FROM categories c ORDER BY c.name
    ")->fetchAll();
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['name'],
            $row['lost_count'],
            $row['found_count'],
            $row['lost_count'] + $row['found_count'],
        ]);
    }

} elseif ($reportType === 'monthly') {
    fputcsv($out, ['Month','Lost Items','Found Items','Net']);

    $lost = $db->query("
        SELECT DATE_FORMAT(date_lost,'%Y-%m') AS month, COUNT(*) AS cnt
        FROM lost_items GROUP BY month ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $found = $db->query("
        SELECT DATE_FORMAT(date_found,'%Y-%m') AS month, COUNT(*) AS cnt
        FROM found_items GROUP BY month ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $months = array_unique(array_merge(array_keys($lost), array_keys($found)));
    sort($months);
    foreach ($months as $m) {
        $l = $lost[$m]  ?? 0;
        $f = $found[$m] ?? 0;
        fputcsv($out, [date('F Y', strtotime($m . '-01')), $l, $f, $f - $l]);
    }
}

fclose($out);
