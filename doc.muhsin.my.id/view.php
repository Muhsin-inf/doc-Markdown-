<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

// Cek lokasi Parsedown.php
$parsedownPath = __DIR__ . '/libs/Parsedown.php';
if (!file_exists($parsedownPath)) {
    $parsedownPath = __DIR__ . '/lib/Parsedown.php';
}

if (!file_exists($parsedownPath)) {
    die("<h2>Error Konfigurasi</h2><p>File <code>Parsedown.php</code> tidak ditemukan di folder <code>libs/</code> atau <code>lib/</code>.</p>");
}

require_once $parsedownPath;
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');
$token = trim($_GET['token'] ?? '');

// Cek dokumen berdasarkan slug atau public token
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
    WHERE (:slug_where != '' AND d.slug = :slug_match)
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
    'slug_where'  => $slug,
    'slug_match'  => $slug
]);
$doc = $stmt->fetch();

if (!$doc || $doc['user_access'] === 'denied') {
    http_response_code(403);
    $pageTitle = '403 Akses Ditolak';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <?php require __DIR__ . '/components/header_meta.php'; ?>
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100">
        <div class="w-full max-w-md border border-slate-200 bg-white p-6 sm:p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <span class="inline-block border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-400">
                403 Akses Ditolak
            </span>
            <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-zinc-50">Akses Tidak Diizinkan</h1>
            <p class="mt-2 text-xs sm:text-sm text-slate-500 dark:text-zinc-400">Dokumen tidak ditemukan atau Anda tidak memiliki izin untuk melihat dokumen ini.</p>
            <div class="mt-6">
                <?php if ($currentUserId > 0): ?>
                    <a href="index.php" class="inline-flex items-center gap-2 border border-blue-600 bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600">
                        ← Kembali ke Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        Masuk ke Akun Anda
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Ambil daftar user yang sudah di-share (khusus Owner)
$sharedUsers = [];
if ($doc['user_access'] === 'owner') {
    $stmtShare = $pdo->prepare("
        SELECT ds.id, ds.permission, u.name, u.email 
        FROM document_shares ds 
        JOIN users u ON ds.user_id = u.id 
        WHERE ds.document_id = ?
        ORDER BY u.name ASC
    ");
    $stmtShare->execute([$doc['id']]);
    $sharedUsers = $stmtShare->fetchAll();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$shareUrl = '';
if ($doc['public_access'] !== 'none' && !empty($doc['share_token'])) {
    $shareUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/view.php?token=' . urlencode($doc['share_token']);
}

$pageTitle = $doc['title'];
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/components/header_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <style>
        .markdown-body pre {
            border-radius: 0px !important;
        }
        .markdown-body code {
            border-radius: 2px !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .markdown-body table {
            border-collapse: collapse;
            width: 100%;
        }
        .markdown-body th, .markdown-body td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
        }
        .dark .markdown-body th, .dark .markdown-body td {
            border-color: #27272a;
        }
        /* Fix: teks panjang tanpa spasi (URL/path) tidak merusak layout mobile */
        .markdown-body,
        .markdown-body p,
        .markdown-body li,
        .markdown-body h1, .markdown-body h2, .markdown-body h3,
        .markdown-body h4, .markdown-body h5, .markdown-body h6 {
            overflow-wrap: break-word;
            word-break: break-word;
        }
        /* Khusus inline code dan link tetap bisa break */
        .markdown-body a {
            word-break: break-all;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex flex-col">

    <?php require __DIR__ . '/components/navbar.php'; ?>

    <!-- Subheader Aksi Dokumen (Sticky di bawah navbar) -->
    <div class="border-b border-slate-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90 py-3">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 sm:px-6">
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <a href="index.php" class="inline-flex items-center gap-1 border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <span class="text-xs text-slate-400 dark:text-zinc-600">/</span>
                <span class="border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    <?= strtoupper(htmlspecialchars($doc['user_access'])) ?>
                </span>
            </div>

            <!-- Tombol Aksi (Edit & Share) -->
            <div class="flex items-center gap-2">
                <?php if (in_array($doc['user_access'], ['owner', 'editor'], true)): ?>
                    <a href="editor.php?id=<?= (int) $doc['id'] ?><?= $token ? '&token=' . urlencode($token) : '' ?>" class="inline-flex items-center gap-1.5 border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                        <svg class="h-3.5 w-3.5 text-slate-500 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                        <span>Edit</span>
                    </a>
                <?php endif; ?>

                <?php if ($doc['user_access'] === 'owner'): ?>
                    <button type="button" onclick="openShareModal()" class="inline-flex items-center gap-1.5 border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                        </svg>
                        <span>Bagikan</span>
                    </button>
                <?php endif; ?>

                <!-- Tombol Download (tersedia untuk semua yang punya akses) -->
                <div class="relative" id="downloadDropdown">
                    <button type="button" onclick="toggleDownloadMenu()" class="inline-flex items-center gap-1.5 border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Unduh</span>
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <!-- Dropdown menu -->
                    <div id="downloadMenu" class="hidden absolute right-0 top-full z-30 mt-1 w-48 border border-slate-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <a href="download.php?id=<?= (int)$doc['id'] ?>&format=md<?= $token ? '&token='.urlencode($token) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Unduh sebagai .md
                        </a>
                        <a href="download.php?id=<?= (int)$doc['id'] ?>&format=txt<?= $token ? '&token='.urlencode($token) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Unduh sebagai .txt
                        </a>
                        <a href="download.php?id=<?= (int)$doc['id'] ?>&format=html<?= $token ? '&token='.urlencode($token) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                            </svg>
                            Unduh .html <span class="ml-auto text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">gambar embed</span>
                        </a>
                        <div class="border-t border-slate-100 dark:border-zinc-800"></div>
                        <a href="download.php?id=<?= (int)$doc['id'] ?>&format=zip<?= $token ? '&token='.urlencode($token) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.75 7.5h16.5M5.625 7.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm14.25 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            Unduh .zip <span class="ml-auto text-[10px] text-blue-600 dark:text-blue-400 font-semibold">md + gambar</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Container Isi Dokumen -->
    <main class="flex-1 mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 sm:py-8 pb-24 md:pb-12">
        <article class="border border-slate-200 bg-white p-6 sm:p-10 dark:border-zinc-800 dark:bg-zinc-900">
            <!-- Header Metadata Dokumen -->
            <div class="border-b border-slate-200 pb-5 mb-6 dark:border-zinc-800">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-zinc-50 mb-2">
                    <?= htmlspecialchars($doc['title']) ?>
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-zinc-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Pemilik: <strong class="text-slate-800 dark:text-zinc-200"><?= htmlspecialchars($doc['owner_name']) ?></strong>
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Diperbarui: <?= date('d M Y, H:i', strtotime($doc['updated_at'])) ?>
                    </span>
                    <?php if ($doc['public_access'] !== 'none'): ?>
                        <span>•</span>
                        <span class="border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                            Public Link: <?= ucfirst($doc['public_access']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Konten Markdown Rendered -->
            <div class="markdown-body prose prose-slate dark:prose-invert max-w-none text-slate-800 dark:text-zinc-200 prose-pre:rounded-none prose-img:border prose-img:border-slate-200 dark:prose-img:border-zinc-800">
                <?= $Parsedown->text($doc['content']) ?>
            </div>
        </article>
    </main>

    <!-- Modal Pengaturan Share (Khusus Pemilik Dokumen) -->
    <?php if ($doc['user_access'] === 'owner'): ?>
    <div id="shareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onclick="if(event.target===this) closeShareModal()">
        <div class="w-full max-w-lg border border-slate-300 bg-white p-5 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900 shadow-xl">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-zinc-100">Bagikan Dokumen</h2>
                </div>
                <button type="button" onclick="closeShareModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 p-1">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form Undang Pengguna Spesifik -->
            <div class="mt-4">
                <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1.5">Undang Pengguna Terdaftar</label>
                <form action="share_action.php" method="POST" class="flex flex-col sm:flex-row gap-2">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($doc['slug']) ?>">
                    
                    <input type="email" name="email" required placeholder="nama@email.com" class="flex-1 border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    
                    <select name="permission" class="border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <option value="viewer">Viewer (Read Only)</option>
                        <option value="editor">Editor</option>
                    </select>

                    <button type="submit" class="border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600">
                        Undang
                    </button>
                </form>
            </div>

            <!-- Daftar Pengguna yang Sudah Diberi Akses -->
            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Anggota Kolaborator</span>
                <div class="mt-2 max-h-36 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800">
                    <?php if (empty($sharedUsers)): ?>
                        <p class="py-2 text-xs text-slate-500 dark:text-zinc-500 italic">Belum ada pengguna yang diundang.</p>
                    <?php else: ?>
                        <?php foreach ($sharedUsers as $su): ?>
                            <div class="flex items-center justify-between py-2 text-xs">
                                <div class="min-w-0 pr-2">
                                    <span class="font-semibold text-slate-800 dark:text-zinc-200"><?= htmlspecialchars($su['name']) ?></span>
                                    <span class="text-slate-400 dark:text-zinc-500 text-[11px]">(<?= htmlspecialchars($su['email']) ?>)</span>
                                    <span class="ml-1 font-mono text-[10px] uppercase text-blue-600 dark:text-blue-400">[<?= htmlspecialchars($su['permission']) ?>]</span>
                                </div>
                                <form action="share_action.php" method="POST" class="shrink-0 m-0">
                                    <input type="hidden" name="action" value="remove_user">
                                    <input type="hidden" name="share_id" value="<?= (int) $su['id'] ?>">
                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($doc['slug']) ?>">
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pengaturan Link Publik & TOMBOL SALIN LINK (Sesuai Permintaan User) -->
            <div class="mt-4 border-t border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                <label class="block text-xs font-semibold text-slate-800 dark:text-zinc-200 mb-1.5">Akses Tautan Publik (Tanpa Perlu Login):</label>
                
                <form action="share_action.php" method="POST" class="flex flex-col sm:flex-row gap-2">
                    <input type="hidden" name="action" value="public_link">
                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($doc['slug']) ?>">
                    
                    <select name="public_access" class="flex-1 border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="none" <?= $doc['public_access'] === 'none' ? 'selected' : '' ?>>Dibatasi (Hanya yang diundang)</option>
                        <option value="viewer" <?= $doc['public_access'] === 'viewer' ? 'selected' : '' ?>>Siapa saja dengan tautan (Viewer)</option>
                        <option value="editor" <?= $doc['public_access'] === 'editor' ? 'selected' : '' ?>>Siapa saja dengan tautan (Editor)</option>
                    </select>

                    <button type="submit" class="border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        Terapkan
                    </button>
                </form>

                <!-- Box Tautan Publik dan Tombol Salin Link -->
                <?php if ($shareUrl !== ''): ?>
                    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-zinc-800">
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-zinc-400 mb-1">Tautan Publik Aktif:</span>
                        <div class="flex items-center gap-2">
                            <input type="text" id="shareUrlInput" readonly value="<?= htmlspecialchars($shareUrl) ?>" onclick="this.select()" class="flex-1 border border-slate-300 bg-white px-2.5 py-1.5 font-mono text-xs text-slate-800 select-all focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            
                            <!-- TOMBOL SALIN LINK (Khusus ditambahkan sesuai request user) -->
                            <button type="button" id="copyShareBtn" onclick="copyShareUrl()" class="shrink-0 inline-flex items-center gap-1.5 border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 dark:border-emerald-500 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                </svg>
                                <span id="copyBtnText">Salin Link</span>
                            </button>
                        </div>
                        <p id="copyFeedback" class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-400 hidden">✓ Link berhasil disalin ke clipboard!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal Footer -->
            <div class="mt-5 flex justify-end">
                <button type="button" onclick="closeShareModal()" class="border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        function openShareModal() {
            document.getElementById('shareModal').classList.remove('hidden');
        }
        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
        }

        // Fungsi Salin Link dengan feedback visual instan
        function copyShareUrl() {
            const input = document.getElementById('shareUrlInput');
            const btnText = document.getElementById('copyBtnText');
            const feedback = document.getElementById('copyFeedback');
            if (!input) return;

            input.select();
            input.setSelectionRange(0, 99999);

            const url = input.value;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(showCopied).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }

            function fallbackCopy() {
                try {
                    document.execCommand('copy');
                    showCopied();
                } catch (err) {
                    alert('Gagal menyalin otomatis, silakan salin manual.');
                }
            }

            function showCopied() {
                if (btnText) btnText.textContent = '✓ Tersalin!';
                if (feedback) feedback.classList.remove('hidden');
                setTimeout(() => {
                    if (btnText) btnText.textContent = 'Salin Link';
                    if (feedback) feedback.classList.add('hidden');
                }, 2500);
            }
        }
    </script>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        hljs.highlightAll();

        // Toggle dropdown unduh
        function toggleDownloadMenu() {
            const menu = document.getElementById('downloadMenu');
            if (menu) menu.classList.toggle('hidden');
        }
        // Tutup dropdown jika klik di luar
        document.addEventListener('click', function(e) {
            const dd = document.getElementById('downloadDropdown');
            if (dd && !dd.contains(e.target)) {
                const menu = document.getElementById('downloadMenu');
                if (menu) menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
