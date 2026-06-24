<?php
// ============================================================
// includes/helpers.php
// Shared utility functions used across all modules
// ============================================================

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateCode(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages = (int) ceil($total / $perPage);
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

function statusBadge(string $status): string {
    $map = [
        'missing'      => 'bg-red-100 text-red-700',
        'matched'      => 'bg-blue-100 text-blue-700',
        'claimed'      => 'bg-yellow-100 text-yellow-700',
        'returned'     => 'bg-green-100 text-green-700',
        'archived'     => 'bg-gray-100 text-gray-500',
        'available'    => 'bg-emerald-100 text-emerald-700',
        'pending'      => 'bg-yellow-100 text-yellow-700',
        'under_review' => 'bg-blue-100 text-blue-700',
        'approved'     => 'bg-green-100 text-green-700',
        'rejected'     => 'bg-red-100 text-red-700',
        'completed'    => 'bg-gray-100 text-gray-600',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-500';
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$class}\">{$label}</span>";
}

function getCategories(): array {
    static $cats = null;
    if ($cats === null) {
        $db   = getDB();
        $stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
        $cats = $stmt->fetchAll();
    }
    return $cats;
}

function getLocations(): array {
    static $locs = null;
    if ($locs === null) {
        $db   = getDB();
        $stmt = $db->query("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");
        $locs = $stmt->fetchAll();
    }
    return $locs;
}
