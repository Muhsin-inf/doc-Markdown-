<?php
/**
 * download.php — Unduh dokumen dalam format .md / .txt / .html (self-contained) / .zip
 * - .md  : file Markdown murni, gambar tetap pakai URL server
 * - .txt : teks biasa (Markdown strip)
 * - .html: rendered HTML dengan gambar di-embed sebagai base64 (bisa dibuka offline)
 * - .zip : .md + semua gambar yang dipakai (bundle lengkap)
 */
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

$currentUserId  = (int) ($_SESSION['user_id'] ?? 0);
$docId          = (int) ($_GET['id'] ?? 0);
$token          = trim($_GET['token'] ?? '');
$format         = trim($_GET['format'] ?? 'md');
$allowedFormats = ['md', 'txt', 'html', 'zip'];

if (!in_array($format, $allowedFormats, true)) {
    $format = 'md';
}

if ($docId <= 0 && $token === '') {
    http_response_code(400);
    exit('Parameter tidak valid.');
}

// ─── Cek akses (sama dengan view.php) ─────────────────────────────────────────
$sql = "
    SELECT d.*, u.name AS owner_name,
        CASE 
            WHEN d.owner_id = :uid_owner THEN 'owner'
            WHEN ds.permission IS NOT NULL THEN ds.permission
            WHEN d.public_access != 'none' AND :token_check != '' AND d.share_token = :token_match THEN d.public_access
            ELSE 'denied'
        END AS user_access
    FROM documents d
    JOIN users u ON d.owner_id = u.id
    LEFT JOIN document_shares ds 
        ON d.id = ds.document_id AND ds.user_id = :uid_share
    WHERE (:id_where > 0 AND d.id = :id_match)
       OR (:token_where != '' AND d.share_token = :token_find)
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'uid_owner'   => $currentUserId,
    'uid_share'   => $currentUserId,
    'token_check' => $token,
    'token_match' => $token,
    'token_find'  => $token,
    'token_where' => $token,
    'id_where'    => $docId,
    'id_match'    => $docId,
]);
$doc = $stmt->fetch();

if (!$doc || $doc['user_access'] === 'denied') {
    http_response_code(403);
    exit('Akses ditolak atau dokumen tidak ditemukan.');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

$safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $doc['title']);
$safeTitle = trim($safeTitle, '_') ?: 'dokumen';
$content   = $doc['content'];

/**
 * Ambil semua URL gambar dalam konten Markdown.
 * Mengembalikan array URL unik.
 */
function extractImageUrls(string $markdown): array {
    $urls = [];
    // Sintaks Markdown: ![alt](url) atau ![alt](url "title")
    preg_match_all('/!\[.*?\]\(([^\s\)]+)(?:\s+"[^"]*")?\)/', $markdown, $matches);
    foreach (($matches[1] ?? []) as $url) {
        $url = trim($url);
        if ($url !== '' && !isset($urls[$url])) {
            $urls[$url] = $url;
        }
    }
    return array_values($urls);
}

/**
 * Konversi URL gambar ke data URI base64.
 * Mendukung URL absolut (http/https) dan path relatif di server.
 */
function urlToBase64DataUri(string $url): ?string {
    // URL relatif → jadikan path di server
    if (!preg_match('/^https?:\/\//i', $url)) {
        $localPath = __DIR__ . '/' . ltrim($url, '/');
        if (!file_exists($localPath)) return null;
        $bytes = @file_get_contents($localPath);
    } else {
        // URL absolut — ambil via file_get_contents (butuh allow_url_fopen)
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $bytes   = @file_get_contents($url, false, $context);
    }

    if ($bytes === false || $bytes === '') return null;

    // Deteksi MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tmpFile = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmpFile, $bytes);
    $mime = finfo_file($finfo, $tmpFile);
    finfo_close($finfo);
    @unlink($tmpFile);

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($mime, $allowed, true)) return null;

    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

/**
 * Ganti URL gambar di konten Markdown dengan data URI base64.
 */
function embedImagesAsBase64(string $markdown): string {
    return preg_replace_callback(
        '/!\[(.*?)\]\(([^\s\)]+)((?:\s+"[^"]*")?)\)/',
        function ($m) {
            $alt   = $m[1];
            $url   = $m[2];
            $title = $m[3];
            $data  = urlToBase64DataUri($url);
            if ($data !== null) {
                return "![{$alt}]({$data}{$title})";
            }
            return $m[0]; // gagal embed, biarkan URL asli
        },
        $markdown
    );
}

// ─── Proses per format ────────────────────────────────────────────────────────

switch ($format) {

    // ── Plain Text ─────────────────────────────────────────────────────────────
    case 'txt':
        $plain = $content;
        $plain = preg_replace('/!\[.*?\]\([^\)]*\)/', '[Gambar]', $plain); // gambar → label
        $plain = preg_replace('/#{1,6}\s+/', '', $plain);
        $plain = preg_replace('/\*\*(.+?)\*\*/', '$1', $plain);
        $plain = preg_replace('/\*(.+?)\*/', '$1', $plain);
        $plain = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $plain);
        $plain = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $plain);
        $plain = preg_replace('/^[-*+]\s+/m', '- ', $plain);
        $plain = preg_replace('/^\d+\.\s+/m', '', $plain);
        $plain = preg_replace('/>\s+/m', '', $plain);
        $plain = preg_replace('/\n{3,}/', "\n\n", $plain);

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.txt"');
        header('Content-Length: ' . strlen($plain));
        echo $plain;
        break;

    // ── HTML Self-Contained (gambar embed base64) ──────────────────────────────
    case 'html':
        $parsedownPath = __DIR__ . '/libs/Parsedown.php';
        if (!file_exists($parsedownPath)) $parsedownPath = __DIR__ . '/lib/Parsedown.php';
        if (!file_exists($parsedownPath)) { http_response_code(500); exit('Parsedown tidak tersedia.'); }

        require_once $parsedownPath;
        $Parsedown = new Parsedown();
        $Parsedown->setSafeMode(false); // nonaktifkan safe mode agar gambar base64 lolos

        // Embed semua gambar sebagai base64 agar HTML bisa dibuka offline
        $embeddedContent = embedImagesAsBase64($content);
        $rendered = $Parsedown->text($embeddedContent);

        $titleEsc     = htmlspecialchars($doc['title']);
        $ownerEsc     = htmlspecialchars($doc['owner_name']);
        $updatedAt    = htmlspecialchars($doc['updated_at']);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleEsc}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 24px; color: #1e293b; line-height: 1.7; }
        h1,h2,h3,h4,h5,h6 { font-weight: 700; margin-top: 1.5em; }
        pre { background:#f1f5f9; padding:16px; overflow:auto; font-size:13px; }
        code { background:#f1f5f9; padding:2px 5px; font-size:13px; }
        pre code { background:none; padding:0; }
        blockquote { border-left:4px solid #cbd5e1; margin:0; padding-left:16px; color:#64748b; }
        table { border-collapse:collapse; width:100%; }
        th,td { border:1px solid #cbd5e1; padding:8px 12px; text-align:left; }
        th { background:#f8fafc; }
        img { max-width:100%; height:auto; }
        a { color:#2563eb; }
        .meta { font-size:12px; color:#94a3b8; margin-bottom:32px; border-bottom:1px solid #e2e8f0; padding-bottom:12px; }
        .note { font-size:11px; color:#94a3b8; text-align:center; margin-top:48px; border-top:1px solid #e2e8f0; padding-top:12px; }
    </style>
</head>
<body>
    <h1>{$titleEsc}</h1>
    <div class="meta">Pemilik: {$ownerEsc} &nbsp;·&nbsp; Diperbarui: {$updatedAt}</div>
    {$rendered}
    <p class="note">Diekspor dari DocManager — Gambar sudah ter-embed di dalam file ini.</p>
</body>
</html>
HTML;

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.html"');
        header('Content-Length: ' . strlen($html));
        echo $html;
        break;

    // ── ZIP Bundle (.md + semua gambar lokal yang dipakai) ────────────────────
    case 'zip':
        // PHP ZipArchive harus tersedia
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('Ekstensi ZipArchive tidak tersedia di server ini.');
        }

        $imageUrls  = extractImageUrls($content);
        $tmpZip     = tempnam(sys_get_temp_dir(), 'doczip') . '.zip';
        $zip        = new ZipArchive();

        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Gagal membuat file ZIP.');
        }

        // Tambahkan file Markdown utama
        $zip->addFromString($safeTitle . '.md', $content);

        // Tambahkan file gambar (yang bisa diakses di server)
        $usedImages   = []; // url → nama file di zip
        $imagesFolded = 'images/';

        foreach ($imageUrls as $url) {
            // Hanya proses gambar yang di-host di server ini (URL relatif / host sama)
            $isLocal = !preg_match('/^https?:\/\//i', $url);
            if (!$isLocal) {
                // Cek apakah URL milik server ini
                $host = $_SERVER['HTTP_HOST'] ?? '';
                $isLocal = (strpos($url, 'http://' . $host) === 0 || strpos($url, 'https://' . $host) === 0);
            }

            if (!$isLocal) {
                // Gambar eksternal → biarkan URL-nya di .md tetap
                continue;
            }

            // Ambil path lokal
            if (preg_match('/^https?:\/\//i', $url)) {
                $path = parse_url($url, PHP_URL_PATH);
                $localPath = __DIR__ . '/' . ltrim($path, '/');
                // Hapus bagian subfolder sebelum uploads
                $uploadsPos = strpos($localPath, '/uploads/');
                if ($uploadsPos !== false) {
                    $localPath = __DIR__ . substr($localPath, $uploadsPos);
                }
            } else {
                $localPath = __DIR__ . '/' . ltrim($url, '/');
            }

            if (!file_exists($localPath)) continue;

            $imgFilename = $imagesFolded . basename($localPath);

            // Hindari nama duplikat
            $counter = 1;
            $baseImgName = $imgFilename;
            while (in_array($imgFilename, $usedImages, true)) {
                $ext         = pathinfo($baseImgName, PATHINFO_EXTENSION);
                $base        = pathinfo($baseImgName, PATHINFO_FILENAME);
                $imgFilename = $imagesFolded . $base . '_' . $counter . '.' . $ext;
                $counter++;
            }

            $zip->addFile($localPath, $imgFilename);
            $usedImages[$url] = $imgFilename;
        }

        // Jika ada gambar yang dimasukkan ke ZIP, buat versi .md yang pakai path relatif
        if (!empty($usedImages)) {
            $mdRelative = $content;
            foreach ($usedImages as $origUrl => $zipPath) {
                // Ganti URL di markdown dengan path relatif ke folder images/
                $mdRelative = str_replace($origUrl, $zipPath, $mdRelative);
            }
            // Ganti file .md utama dengan versi path relatif
            $zip->deleteName($safeTitle . '.md');
            $zip->addFromString($safeTitle . '.md', $mdRelative);

            // Tambahkan README ringkas
            $readme = "# {$doc['title']}\n\n"
                    . "File ZIP ini berisi:\n"
                    . "- `{$safeTitle}.md` — dokumen utama (gambar pakai path relatif)\n"
                    . "- `images/` — folder berisi semua gambar yang dipakai dokumen\n\n"
                    . "Buka file `.md` dengan editor Markdown seperti Typora, Obsidian, atau VS Code.\n";
            $zip->addFromString('README.md', $readme);
        }

        $zip->close();

        // Kirim file ZIP ke browser
        $zipSize = filesize($tmpZip);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.zip"');
        header('Content-Length: ' . $zipSize);
        header('Cache-Control: no-cache');
        readfile($tmpZip);
        @unlink($tmpZip);
        break;

    // ── Markdown Murni ─────────────────────────────────────────────────────────
    case 'md':
    default:
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.md"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        break;
}
exit;
