<?php
/**
 * upload_image.php — API endpoint upload gambar untuk editor Markdown.
 * Menyimpan gambar ke uploads/images/{user_id}/ dan mengembalikan URL-nya.
 * Response: JSON { success, url, error }
 */
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

header('Content-Type: application/json');

// Hanya izinkan user yang sudah login
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Belum login.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tidak ada file yang dikirim.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$file   = $_FILES['image'];

// Validasi error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server).',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar.',
        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap, coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp server tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file di server.',
        UPLOAD_ERR_EXTENSION  => 'Upload diblokir oleh ekstensi PHP.',
    ];
    echo json_encode(['success' => false, 'error' => $errMessages[$file['error']] ?? 'Error upload tidak diketahui.']);
    exit;
}

// Validasi ukuran (maks 5 MB)
$maxSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Ukuran gambar maksimal 5 MB.']);
    exit;
}

// Validasi tipe file — cek MIME type sungguhan (bukan hanya ekstensi)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];

if (!isset($allowedMimes[$mimeType])) {
    echo json_encode(['success' => false, 'error' => 'Format gambar tidak didukung. Gunakan JPG, PNG, GIF, WebP, atau SVG.']);
    exit;
}

// Buat folder tujuan per user
$ext       = $allowedMimes[$mimeType];
$uploadDir = __DIR__ . '/uploads/images/' . $userId . '/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Gagal membuat folder upload di server.']);
        exit;
    }
}

// Buat nama file unik: timestamp + random + user id
$filename = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '_u' . $userId . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file di server.']);
    exit;
}

// Susun URL publik yang bisa dipakai di Markdown
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$baseDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$fileUrl = $protocol . '://' . $host . $baseDir . '/uploads/images/' . $userId . '/' . $filename;

echo json_encode([
    'success'  => true,
    'url'      => $fileUrl,
    'filename' => $filename,
    'size'     => $file['size'],
    'mime'     => $mimeType,
]);
exit;
