<?php
// ============================================================
// config/app.php
// Application-wide constants
// ============================================================

define('APP_NAME', 'LostFound');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/lostfound');
define('BASE_PATH', 'C:/xampp/htdocs/lostfound');

// Upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_EXTS', ['jpg', 'jpeg', 'png', 'webp']);

// Upload directories
define('UPLOAD_LOST',     BASE_PATH . '/uploads/lost_items/');
define('UPLOAD_FOUND',    BASE_PATH . '/uploads/found_items/');
define('UPLOAD_EVIDENCE', BASE_PATH . '/uploads/claims/');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour

// Pagination
define('ITEMS_PER_PAGE', 15);

// Matching threshold
define('MATCH_THRESHOLD', 40); // min score to suggest match
