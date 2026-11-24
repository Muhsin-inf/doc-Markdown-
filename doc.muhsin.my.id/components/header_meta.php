<?php
// components/header_meta.php
$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - DocManager' : 'DocManager - Dokumentasi Terstruktur';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>

<!-- PWA & Mobile Web App Meta -->
<link rel="manifest" href="manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="DocManager">
<meta name="application-name" content="DocManager">
<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">

<!-- Favicons & Touch Icons -->
<link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">

<!-- CSS Lokal Terkompilasi (Tailwind CSS Mandiri Tanpa CDN) -->
<link rel="stylesheet" href="assets/css/style.css">

<script>
    // Inisialisasi tema segera di <head> untuk mencegah FOUC (flash of unstyled content)
    (function() {
        try {
            const savedTheme = localStorage.getItem('doc_theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } catch (e) {}
    })();

    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('doc_theme', isDark ? 'dark' : 'light');
        } catch (e) {}
        window.dispatchEvent(new CustomEvent('themechanged', { detail: { dark: isDark } }));
    }

    // Registrasi Service Worker untuk PWA (Browser App Installable)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./sw.js')
                .then((reg) => {
                    console.log('DocManager PWA: Service Worker terdaftar dengan scope:', reg.scope);
                })
                .catch((err) => {
                    console.warn('DocManager PWA: Pendaftaran Service Worker gagal:', err);
                });
        });
    }

    // Tangani event beforeinstallprompt untuk install aplikasi ke browser/desktop/HP
    window.deferredPWAInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.deferredPWAInstallPrompt = e;
        // Munculkan tombol install jika elemen tersedia
        document.querySelectorAll('.pwa-install-btn').forEach((btn) => {
            btn.classList.remove('hidden');
        });
    });

    window.addEventListener('appinstalled', () => {
        window.deferredPWAInstallPrompt = null;
        document.querySelectorAll('.pwa-install-btn').forEach((btn) => {
            btn.classList.add('hidden');
        });
        console.log('DocManager PWA: Berhasil diinstall sebagai aplikasi!');
    });

    function installPWAApp() {
        if (!window.deferredPWAInstallPrompt) {
            alert('Aplikasi sudah terpasang atau browser Anda dapat menginstalnya melalui menu browser (titik tiga -> Pasang/Install Aplikasi).');
            return;
        }
        window.deferredPWAInstallPrompt.prompt();
        window.deferredPWAInstallPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User menyetujui instalasi PWA.');
            }
            window.deferredPWAInstallPrompt = null;
        });
    }
</script>
