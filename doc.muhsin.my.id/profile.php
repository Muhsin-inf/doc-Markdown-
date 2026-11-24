<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';
requireLogin($pdo);

$user = getCurrentUser($pdo);
if (!$user) {
    header("Location: logout.php");
    exit;
}

$userId = (int) $user['id'];
$successMessage = '';
$errorMessage = '';

// Cek apakah kolom avatar sudah ada, jika belum coba buat secara aman
try {
    $pdo->query("SELECT avatar FROM users LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
    } catch (Exception $ex) {
        // Abaikan jika tidak memiliki hak akses alter table
    }
}

// Tangani Update Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || $email === '') {
            $errorMessage = "Nama dan email tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Format email tidak valid.";
        } else {
            // Cek apakah email sudah dipakai user lain
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $checkStmt->execute([$email, $userId]);
            if ($checkStmt->fetch()) {
                $errorMessage = "Email sudah digunakan oleh akun lain.";
            } else {
                $avatarPath = $user['avatar'];

                // Tangani hapus avatar
                if (!empty($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
                    if (!empty($avatarPath) && file_exists(__DIR__ . '/' . $avatarPath)) {
                        @unlink(__DIR__ . '/' . $avatarPath);
                    }
                    $avatarPath = null;
                }

                // Tangani upload avatar baru
                if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['avatar_file']['tmp_name'];
                    $fileSize = $_FILES['avatar_file']['size'];
                    $fileType = mime_content_type($fileTmp);
                    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                    if (!array_key_exists($fileType, $allowedTypes)) {
                        $errorMessage = "Format file avatar harus JPG, PNG, atau WebP.";
                    } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB
                        $errorMessage = "Ukuran file avatar maksimal 2MB.";
                    } else {
                        $uploadDir = __DIR__ . '/uploads/avatars';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        // Hapus avatar lama jika ada
                        if (!empty($avatarPath) && file_exists(__DIR__ . '/' . $avatarPath)) {
                            @unlink(__DIR__ . '/' . $avatarPath);
                        }

                        $ext = $allowedTypes[$fileType];
                        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        $destination = $uploadDir . '/' . $filename;

                        if (move_uploaded_file($fileTmp, $destination)) {
                            $avatarPath = 'uploads/avatars/' . $filename;
                        } else {
                            $errorMessage = "Gagal mengunggah foto profil.";
                        }
                    }
                }

                if ($errorMessage === '') {
                    try {
                        $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, avatar = ? WHERE id = ?");
                        $updateStmt->execute([$name, $email, $avatarPath, $userId]);
                    } catch (Exception $e) {
                        // Jika kolom avatar tidak ada
                        $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                        $updateStmt->execute([$name, $email, $userId]);
                    }

                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_avatar'] = $avatarPath;
                    $successMessage = "Profil berhasil diperbarui.";
                    $user = getCurrentUser($pdo); // Refresh data
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $hasExistingPassword = (bool) $user['has_password'];

        if ($hasExistingPassword) {
            // Ambil hash password saat ini
            $pwStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $pwStmt->execute([$userId]);
            $userData = $pwStmt->fetch();

            if (!$userData || !password_verify($currentPassword, $userData['password'])) {
                $errorMessage = "Password saat ini tidak cocok.";
            }
        }

        if ($errorMessage === '') {
            if (strlen($newPassword) < 6) {
                $errorMessage = "Password baru minimal 6 karakter.";
            } elseif ($newPassword !== $confirmPassword) {
                $errorMessage = "Konfirmasi password baru tidak cocok.";
            } else {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updatePw->execute([$hashed, $userId]);
                $successMessage = "Password berhasil diubah.";
                $user = getCurrentUser($pdo); // Refresh data
            }
        }
    }
}

// Statistik Dokumen Milik Pengguna
$stmtStats = $pdo->prepare("SELECT COUNT(*) AS total_docs FROM documents WHERE owner_id = ?");
$stmtStats->execute([$userId]);
$totalDocs = (int) $stmtStats->fetchColumn();

$stmtShares = $pdo->prepare("SELECT COUNT(*) AS total_shares FROM document_shares ds JOIN documents d ON ds.document_id = d.id WHERE d.owner_id = ?");
$stmtShares->execute([$userId]);
$totalShares = (int) $stmtShares->fetchColumn();

$pageTitle = 'Profil Saya';
$activeNav = 'profile';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/components/header_meta.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex flex-col">

    <?php require __DIR__ . '/components/navbar.php'; ?>

    <!-- Main Content Container (dengan pb-24 agar tidak tertutup bottom navbar di mobile) -->
    <main class="flex-1 mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 sm:py-8 pb-24 md:pb-12">
        
        <!-- Header Judul Halaman -->
        <div class="mb-6 border-b border-slate-200 pb-4 dark:border-zinc-800">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-zinc-50">Pengaturan Profil</h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1">Kelola identitas akun, foto profil, dan keamanan Anda.</p>
        </div>

        <!-- Alert Pesan Sukses / Error -->
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 flex items-center gap-3 border border-emerald-500/30 bg-emerald-50 px-4 py-3 text-xs sm:text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-950/30 dark:text-emerald-300">
                <svg class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 flex items-center gap-3 border border-rose-500/30 bg-rose-50 px-4 py-3 text-xs sm:text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-950/30 dark:text-rose-300">
                <svg class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            
            <!-- Kolom Kiri: Kartu Ringkasan Akun & Statistik -->
            <div class="space-y-6">
                <!-- Kartu Identitas Akun -->
                <div class="border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center gap-4">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="h-16 w-16 object-cover border border-slate-300 dark:border-zinc-700">
                        <?php else: ?>
                            <div class="flex h-16 w-16 items-center justify-center border border-slate-300 bg-blue-600 text-2xl font-bold text-white dark:border-zinc-700 dark:bg-blue-500">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-slate-900 truncate dark:text-zinc-100"><?= htmlspecialchars($user['name']) ?></h2>
                            <p class="text-xs text-slate-500 truncate dark:text-zinc-400"><?= htmlspecialchars($user['email']) ?></p>
                            <span class="inline-block mt-2 border border-slate-300 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                <?= strtoupper(htmlspecialchars($user['role'] ?? 'user')) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4 text-xs space-y-2 dark:border-zinc-800 text-slate-600 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span>Metode Login</span>
                            <span class="font-medium text-slate-800 dark:text-zinc-200">
                                <?= !empty($user['oauth_provider']) ? ucfirst(htmlspecialchars($user['oauth_provider'])) : 'Email & Password' ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Bergabung Sejak</span>
                            <span class="font-medium text-slate-800 dark:text-zinc-200">
                                <?= date('d M Y', strtotime($user['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Kartu Statistik -->
                <div class="border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-4">Aktivitas Saya</h3>
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="border border-slate-100 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= $totalDocs ?></div>
                            <div class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Dokumen Dibuat</div>
                        </div>
                        <div class="border border-slate-100 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="text-2xl font-bold text-slate-800 dark:text-zinc-200"><?= $totalShares ?></div>
                            <div class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Dibagikan</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Form Ubah Profil & Keamanan -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Form 1: Biodata & Foto Profil -->
                <section class="border border-slate-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-zinc-100 mb-1">Informasi Dasar</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-5">Perbarui nama tampilan, email, serta foto profil Anda.</p>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Input Upload Avatar -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-2">Foto Profil (Avatar)</label>
                            <div class="flex flex-wrap items-center gap-4">
                                <div id="avatarPreviewBox" class="h-14 w-14 shrink-0 border border-slate-300 dark:border-zinc-700 overflow-hidden bg-slate-100 dark:bg-zinc-800 flex items-center justify-center">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img id="avatarPreviewImg" src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="h-full w-full object-cover">
                                    <?php else: ?>
                                        <span id="avatarPreviewText" class="text-lg font-bold text-slate-500 dark:text-zinc-400">
                                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-[200px] space-y-2">
                                    <input type="file" name="avatar_file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:border file:border-slate-300 file:text-xs file:font-semibold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100 dark:file:border-zinc-700 dark:file:bg-zinc-800 dark:file:text-zinc-300 dark:hover:file:bg-zinc-700 cursor-pointer">
                                    <div class="text-[11px] text-slate-400 dark:text-zinc-500">Maks. 2MB (JPG, PNG, atau WebP). Sudut tajam tanpa border-radius berlebih.</div>
                                </div>

                                <?php if (!empty($user['avatar'])): ?>
                                    <label class="flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                                        <input type="checkbox" name="remove_avatar" value="1" class="h-3.5 w-3.5 border-slate-300 dark:border-zinc-700 text-rose-600">
                                        <span>Hapus foto saat ini</span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Input Nama Lengkap -->
                        <div>
                            <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Nama Lengkap</label>
                            <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['name']) ?>" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </div>

                        <!-- Input Email -->
                        <div>
                            <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Alamat Email</label>
                            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="border border-blue-600 bg-blue-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                                Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Form 2: Keamanan / Ubah Password -->
                <section class="border border-slate-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-zinc-100 mb-1">Ubah Password</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-5">
                        <?= $user['has_password'] ? 'Perbarui kata sandi untuk melindungi akses akun Anda.' : 'Anda belum memiliki password lokal (login menggunakan Google). Anda dapat menyetel password untuk opsi login manual.' ?>
                    </p>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">

                        <?php if ($user['has_password']): ?>
                        <div>
                            <label for="current_password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" required placeholder="••••••••" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="new_password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Password Baru</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Ulangi Password Baru</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Ketik ulang password baru" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="border border-slate-300 bg-slate-100 px-4 py-2 text-xs sm:text-sm font-semibold text-slate-800 hover:bg-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                <?= $user['has_password'] ? 'Perbarui Password' : 'Setel Password Akun' ?>
                            </button>
                        </div>
                    </form>
                </section>

            </div>
        </div>

    </main>

    <script>
        // Preview avatar saat memilih file lokal
        const avatarInput = document.getElementById('avatarFileInput');
        if (avatarInput) {
            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const box = document.getElementById('avatarPreviewBox');
                        box.innerHTML = `<img src="${event.target.result}" class="h-full w-full object-cover">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
