<?php
require 'config/database.php';
require 'config/auth.php';
requireLogin($pdo);

$userId = (int) $_SESSION['user_id'];
$search = trim($_GET['q'] ?? '');

// Ambil dokumen milik sendiri + yang di-share ke user ini
$sql = "
    SELECT d.*, u.name AS owner_name,
        CASE 
            WHEN d.owner_id = :uid1 THEN 'owner'
            ELSE ds.permission 
        END AS access_level
    FROM documents d
    JOIN users u ON d.owner_id = u.id
    LEFT JOIN document_shares ds ON d.id = ds.document_id AND ds.user_id = :uid2
    WHERE (d.owner_id = :uid3 OR ds.user_id = :uid4)
";

$params = ['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId, 'uid4' => $userId];

if ($search !== '') {
    $sql .= " AND (d.title LIKE :search OR d.content LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY d.updated_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Hitung total dokumen berdasarkan role
$totalCount = count($documents);
$ownedCount = 0;
$sharedCount = 0;
foreach ($documents as $item) {
    if ($item['access_level'] === 'owner') {
        $ownedCount++;
    } else {
        $sharedCount++;
    }
}

$pageTitle = 'Dashboard Dokumentasi';
$activeNav = 'index';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/components/header_meta.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex flex-col">

    <?php require __DIR__ . '/components/navbar.php'; ?>

    <main class="flex-1 mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-8 pb-24 md:pb-12">
        
        <!-- Header Atas Dashboard & Statistik Mini -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4 dark:border-zinc-800">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-zinc-50">Koleksi Dokumentasi</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Kelola dokumen, catatan markdown, dan kolaborasi tim Anda.</p>
            </div>

            <!-- Tombol Buat Baru -->
            <a href="editor.php" class="inline-flex items-center justify-center gap-2 border border-blue-600 bg-blue-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Buat / Upload .md</span>
            </a>
        </div>

        <!-- Toolbar Pencarian & Filter Status -->
        <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <form method="GET" class="flex flex-1 gap-2">
                <div class="relative flex-1">
                    <input type="text" name="q" placeholder="Cari judul atau isi dokumentasi..." value="<?= htmlspecialchars($search) ?>" class="w-full border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <button type="submit" class="border border-slate-300 bg-white px-4 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    Cari
                </button>
                <?php if ($search !== ''): ?>
                    <a href="index.php" class="border border-slate-200 bg-slate-100 px-3 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-200 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-300 flex items-center justify-center">
                        Reset
                    </a>
                <?php endif; ?>
            </form>

            <!-- Badge Jumlah -->
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-zinc-400">
                <span class="border border-slate-200 bg-slate-100 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-900">
                    Total: <strong class="text-slate-800 dark:text-zinc-200"><?= $totalCount ?></strong>
                </span>
                <span class="border border-slate-200 bg-slate-100 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-900">
                    Milik Sendiri: <strong class="text-slate-800 dark:text-zinc-200"><?= $ownedCount ?></strong>
                </span>
                <span class="border border-slate-200 bg-slate-100 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-900">
                    Dibagikan: <strong class="text-slate-800 dark:text-zinc-200"><?= $sharedCount ?></strong>
                </span>
            </div>
        </div>

        <!-- Daftar Dokumen -->
        <div class="border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 divide-y divide-slate-100 dark:divide-zinc-800">
            <?php if (empty($documents)): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-zinc-600 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-sm font-semibold text-slate-700 dark:text-zinc-300">
                        <?= $search !== '' ? 'Tidak ada dokumen yang cocok dengan pencarian "' . htmlspecialchars($search) . '".' : 'Belum ada dokumen yang tersedia.' ?>
                    </p>
                    <p class="text-xs text-slate-400 dark:text-zinc-500 mt-1">Mulai tulis dokumentasi baru Anda sekarang.</p>
                    <a href="editor.php" class="mt-4 inline-flex items-center gap-1.5 border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600">
                        + Buat Dokumen Pertama
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:px-6 hover:bg-slate-50/70 dark:hover:bg-zinc-800/50 transition-colors gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="view.php?slug=<?= urlencode($doc['slug']) ?>" class="text-sm sm:text-base font-semibold text-slate-900 hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400 truncate">
                                    <?= htmlspecialchars($doc['title']) ?>
                                </a>
                                <?php if ($doc['public_access'] !== 'none'): ?>
                                    <span title="Dokumen ini memiliki tautan publik" class="border border-emerald-300 bg-emerald-50 px-1.5 py-0.2 text-[10px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        Publik
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-zinc-400">
                                <span>Pemilik: <strong class="text-slate-700 dark:text-zinc-300"><?= htmlspecialchars($doc['owner_name']) ?></strong></span>
                                <span>•</span>
                                <span>Diperbarui: <?= date('d M Y, H:i', strtotime($doc['updated_at'])) ?></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <!-- Access Badge (Minimalist square) -->
                            <?php if ($doc['access_level'] === 'owner'): ?>
                                <span class="border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-400">
                                    OWNER
                                </span>
                            <?php elseif ($doc['access_level'] === 'editor'): ?>
                                <span class="border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-400">
                                    EDITOR
                                </span>
                            <?php else: ?>
                                <span class="border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                    VIEWER
                                </span>
                            <?php endif; ?>

                            <!-- Tombol Aksi Buka & Edit -->
                            <a href="view.php?slug=<?= urlencode($doc['slug']) ?>" class="border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                Buka
                            </a>

                            <?php if ($doc['access_level'] === 'owner' || $doc['access_level'] === 'editor'): ?>
                                <a href="editor.php?id=<?= (int) $doc['id'] ?>" class="border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                    Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

</body>
</html>
