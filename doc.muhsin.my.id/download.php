<?php
/**
 * download.php — Unduh dokumen dalam format .md / .txt / .html
 * Mendukung akses via token publik (tanpa login) atau session user.
 */
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$docId  = (int) ($_GET['id'] ?? 0);
$token  = trim($_GET['token'] ?? '');
$format = trim($_GET['format'] ?? 'md');
$allowedFormats = ['md', 'txt', 'html'];
if (!in_array($format, $allowedFormats, true)) {
    $format = 'md';
}

if ($docId <= 0 && $token === '') {
    http_response_code(400);
    exit('Parameter tidak valid.');
}

// Cek akses — sama dengan logika view.php
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

// Bersihkan judul untuk nama file
$safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $doc['title']);
$safeTitle = trim($safeTitle, '_') ?: 'dokumen';

$content = $doc['content'];

switch ($format) {
    case 'txt':
        // Plain text — hapus Markdown syntax dasar
        $plain = $content;
        $plain = preg_replace('/#{1,6}\s+/', '', $plain);       // heading #
        $plain = preg_replace('/\*\*(.+?)\*\*/', '$1', $plain); // bold
        $plain = preg_replace('/\*(.+?)\*/', '$1', $plain);     // italic
        $plain = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $plain); // code
        $plain = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $plain); // link
        $plain = preg_replace('/!\[.*?\]\(.+?\)/', '', $plain);    // image
        $plain = preg_replace('/^[-*+]\s+/m', '- ', $plain);      // list
        $plain = preg_replace('/^\d+\.\s+/m', '', $plain);        // numbered list
        $plain = preg_replace('/>\s+/m', '', $plain);             // blockquote
        $plain = preg_replace('/\n{3,}/', "\n\n", $plain);        // extra newlines

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.txt"');
        header('Content-Length: ' . strlen($plain));
        echo $plain;
        break;

    case 'html':
        // Rendered HTML dengan styling minimal
        $parsedownPath = __DIR__ . '/libs/Parsedown.php';
        if (!file_exists($parsedownPath)) {
            $parsedownPath = __DIR__ . '/lib/Parsedown.php';
        }
        if (!file_exists($parsedownPath)) {
            http_response_code(500);
            exit('Parsedown tidak tersedia.');
        }
        require_once $parsedownPath;
        $Parsedown = new Parsedown();
        $Parsedown->setSafeMode(true);
        $rendered = $Parsedown->text($content);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$doc['title']}</title>
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
        img { max-width:100%; }
        a { color:#2563eb; }
        .meta { font-size:12px; color:#94a3b8; margin-bottom:32px; border-bottom:1px solid #e2e8f0; padding-bottom:12px; }
    </style>
</head>
<body>
    <h1>{$doc['title']}</h1>
    <div class="meta">Pemilik: {$doc['owner_name']} &nbsp;·&nbsp; Diperbarui: {$doc['updated_at']}</div>
    {$rendered}
</body>
</html>
HTML;

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.html"');
        header('Content-Length: ' . strlen($html));
        echo $html;
        break;

    case 'md':
    default:
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.md"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        break;
}
exit;
