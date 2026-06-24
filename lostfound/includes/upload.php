<?php
// ============================================================
// includes/upload.php
// C:/xampp/htdocs/lostfound/includes/upload.php
// Handles all file uploads securely
// ============================================================

function uploadImage(array $file, string $destination): array {
    // $file = $_FILES['field']
    // $destination = UPLOAD_LOST | UPLOAD_FOUND | UPLOAD_EVIDENCE

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error.'];
    }

    // Validate size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size must not exceed 5MB.'];
    }

    // Validate MIME type using finfo
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTS, true)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    // Generate safe filename
    $filename = uniqid('img_', true) . '.' . $ext;
    $destPath = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $filename;

    // Create directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
    }

    return ['success' => true, 'filename' => $filename];
}

function deleteUpload(string $filename, string $directory): void {
    if (empty($filename)) return;
    $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($path)) {
        unlink($path);
    }
}

function getImageUrl(string $filename, string $type = 'lost'): string {
    $dirs = [
        'lost'     => BASE_URL . '/uploads/lost_items/',
        'found'    => BASE_URL . '/uploads/found_items/',
        'evidence' => BASE_URL . '/uploads/claims/',
    ];
    $base = $dirs[$type] ?? $dirs['lost'];
    return $base . rawurlencode($filename);
}
