<?php
// components/navbar.php
$activeNav = $activeNav ?? basename($_SERVER['PHP_SELF'], '.php');
$isLoggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'User';
$userInitial = strtoupper(substr($userName, 0, 1));
$userAvatar = $_SESSION['user_avatar'] ?? null;
?>

<!-- ==================== DESKTOP TOP NAVBAR ==================== -->
<header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
    <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
        <!-- Sisi Kiri: Brand & Menu Navigasi Utama -->
        <div class="flex items-center gap-6">
            <a href="index.php" class="flex items-center gap-2 font-bold tracking-tight text-slate-900 hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400">
                <span class="flex h-7 w-7 items-center justify-center border border-slate-300 bg-slate-100 text-xs font-black dark:border-zinc-700 dark:bg-zinc-800">
                    DM
                </span>
                <span class="text-base font-semibold">DocManager</span>
            </a>

            <?php if ($isLoggedIn): ?>
            <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
                <a href="index.php" class="px-3 py-1.5 transition-colors border-b-2 <?= $activeNav === 'index' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100' ?>">
                    Semua Dokumen
                </a>
                <a href="editor.php" class="px-3 py-1.5 transition-colors border-b-2 <?= $activeNav === 'editor' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100' ?>">
                    + Buat Dokumen
                </a>
            </nav>
            <?php endif; ?>
        </div>

        <!-- Sisi Kanan: Install PWA, Theme Toggle & User Menu -->
        <div class="flex items-center gap-2 sm:gap-3">
            
            <!-- Tombol Install Aplikasi Browser (PWA) -->
            <button type="button" onclick="installPWAApp()" title="Install DocManager sebagai aplikasi desktop / browser" class="pwa-install-btn hidden inline-flex items-center gap-1.5 border border-emerald-600/40 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/40">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span class="hidden sm:inline">Install App</span>
            </button>

            <!-- Theme Toggle Button (Desktop & Mobile Top) -->
            <button type="button" onclick="toggleTheme()" aria-label="Ganti Tema Dark/Light" title="Ganti Tema Dark/Light" class="flex h-9 w-9 items-center justify-center border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-800/80 dark:text-zinc-300 dark:hover:bg-zinc-700">
                <!-- Sun Icon (Tampil saat Dark) -->
                <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                <!-- Moon Icon (Tampil saat Light) -->
                <svg class="block h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
            </button>

            <?php if ($isLoggedIn): ?>
                <!-- Quick Desktop Create Button -->
                <a href="editor.php" class="hidden sm:inline-flex items-center gap-1.5 border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Tulis .md</span>
                </a>

                <!-- Desktop User Menu Dropdown / Direct Link -->
                <div class="relative group">
                    <button type="button" class="flex items-center gap-2 border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-800 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                        <?php if (!empty($userAvatar)): ?>
                            <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="h-6 w-6 object-cover border border-slate-300 dark:border-zinc-700">
                        <?php else: ?>
                            <span class="flex h-6 w-6 items-center justify-center border border-slate-300 bg-blue-600 text-[11px] font-bold text-white dark:border-zinc-700 dark:bg-blue-500">
                                <?= $userInitial ?>
                            </span>
                        <?php endif; ?>
                        <span class="hidden md:inline max-w-[120px] truncate"><?= htmlspecialchars($userName) ?></span>
                        <svg class="h-3 w-3 text-slate-500 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div class="invisible group-hover:visible group-focus-within:visible opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-all duration-150 absolute right-0 top-full mt-1 w-44 border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 z-50">
                        <div class="border-b border-slate-100 px-3 py-2 text-xs text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                            Masuk sebagai<br>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200"><?= htmlspecialchars($userName) ?></span>
                        </div>
                        <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            Profil & Akun
                        </a>
                        <a href="index.php" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            Daftar Dokumen
                        </a>
                        <button type="button" onclick="installPWAApp()" class="pwa-install-btn hidden w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-zinc-800 text-left">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Install Aplikasi
                        </button>
                        <div class="border-t border-slate-100 dark:border-zinc-800"></div>
                        <a href="logout.php" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            Keluar
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    Masuk
                </a>
                <a href="register.php" class="border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600">
                    Daftar
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ==================== MOBILE BOTTOM APP-STYLE NAVBAR ==================== -->
<!-- Sesuai permintaan user: Di layar mobile navbar berada di bawah seperti style aplikasi mobile -->
<?php if ($isLoggedIn): ?>
<nav aria-label="Navigasi Bawah Mobile" class="fixed bottom-0 inset-x-0 z-50 border-t border-slate-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95 md:hidden">
    <div class="grid grid-cols-4 h-14">
        <!-- Tab 1: Dokumen / Beranda -->
        <a href="index.php" class="flex flex-col items-center justify-center gap-1 transition-colors <?= $activeNav === 'index' ? 'text-blue-600 dark:text-blue-400 font-bold border-t-2 border-blue-600 dark:border-blue-400 bg-slate-50/50 dark:bg-zinc-900/50' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-zinc-100' ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span class="text-[10px] tracking-tight">Dokumen</span>
        </a>

        <!-- Tab 2: Buat Baru -->
        <a href="editor.php" class="flex flex-col items-center justify-center gap-1 transition-colors <?= $activeNav === 'editor' ? 'text-blue-600 dark:text-blue-400 font-bold border-t-2 border-blue-600 dark:border-blue-400 bg-slate-50/50 dark:bg-zinc-900/50' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-zinc-100' ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            <span class="text-[10px] tracking-tight">Buat</span>
        </a>

        <!-- Tab 3: Tema (Toggle langsung dari bottom bar) -->
        <button type="button" onclick="toggleTheme()" class="flex flex-col items-center justify-center gap-1 text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-zinc-100 transition-colors">
            <svg class="hidden h-5 w-5 dark:block text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <svg class="block h-5 w-5 dark:hidden text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            <span class="text-[10px] tracking-tight">Tema</span>
        </button>

        <!-- Tab 4: Profil Saya -->
        <a href="profile.php" class="flex flex-col items-center justify-center gap-1 transition-colors <?= $activeNav === 'profile' ? 'text-blue-600 dark:text-blue-400 font-bold border-t-2 border-blue-600 dark:border-blue-400 bg-slate-50/50 dark:bg-zinc-900/50' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-zinc-100' ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            <span class="text-[10px] tracking-tight">Profil</span>
        </a>
    </div>
</nav>
<?php endif; ?>
