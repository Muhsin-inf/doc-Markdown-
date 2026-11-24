<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/oauth.php';

if (empty($_SESSION['user_id'])) {
    checkRememberMeLogin($pdo);
}

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if ($emailInput === '' || $password === '') {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$emailInput]);
        $user = $stmt->fetch();

        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? null;

            if ($rememberMe) {
                createRememberToken($pdo, (int) $user['id']);
            } else {
                clearRememberCookie();
            }

            header("Location: index.php");
            exit;
        } elseif ($user && empty($user['password']) && !empty($user['oauth_provider'])) {
            $error = "Akun ini terdaftar menggunakan " . ucfirst($user['oauth_provider']) . ". Silakan login dengan tombol Google di bawah.";
        } else {
            $error = "Email atau password salah.";
        }
    }
}

$pageTitle = 'Masuk ke Akun';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/components/header_meta.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex flex-col justify-center items-center p-4">

    <!-- Theme Toggle Floating di Pojok Kanan Atas -->
    <div class="fixed top-4 right-4 z-10">
        <button type="button" onclick="toggleTheme()" title="Ganti Tema" class="flex h-9 w-9 items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
            <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <svg class="block h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
        </button>
    </div>

    <!-- Login Card (Boxy minimalist) -->
    <div class="w-full max-w-sm border border-slate-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900 shadow-sm">
        <!-- Logo & Judul -->
        <div class="mb-6 text-center">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center border border-slate-300 bg-slate-100 text-sm font-black dark:border-zinc-700 dark:bg-zinc-800">
                DM
            </div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-zinc-50">Masuk ke DocManager</h1>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Kelola dan baca dokumentasi Markdown Anda</p>
        </div>

        <!-- Alert Error -->
        <?php if ($error !== ''): ?>
            <div class="mb-4 border border-rose-500/30 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-500/20 dark:bg-rose-950/30 dark:text-rose-300">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" class="space-y-4">
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Email</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($emailInput) ?>" placeholder="nama@email.com" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
            </div>

            <div class="flex items-center">
                <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-zinc-400 cursor-pointer">
                    <input type="checkbox" name="remember_me" value="1" class="h-3.5 w-3.5 border-slate-300 dark:border-zinc-700 text-blue-600">
                    <span>Ingat saya selama <?= REMEMBER_LIFETIME_DAYS ?> hari</span>
                </label>
            </div>

            <button type="submit" class="w-full border border-blue-600 bg-blue-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                Login
            </button>
        </form>

        <!-- Divider Atau -->
        <div class="relative my-5 flex items-center justify-center">
            <div class="w-full border-t border-slate-200 dark:border-zinc-800"></div>
            <span class="absolute bg-white px-2 text-[11px] text-slate-400 dark:bg-zinc-900 dark:text-zinc-500">atau</span>
        </div>

        <!-- Tombol Google OAuth -->
        <a href="<?= htmlspecialchars(getGoogleAuthUrl()) ?>" class="flex w-full items-center justify-center gap-2 border border-slate-300 bg-white px-4 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800">
            <svg class="h-4 w-4" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Masuk dengan Google</span>
        </a>

        <!-- Footer Link Registrasi -->
        <p class="mt-6 text-center text-xs text-slate-500 dark:text-zinc-400">
            Belum punya akun? 
            <a href="register.php" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">Daftar Akun Baru</a>
        </p>
    </div>

</body>
</html>
