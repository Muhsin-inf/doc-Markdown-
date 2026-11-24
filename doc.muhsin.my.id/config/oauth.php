<?php
require_once __DIR__ . '/env.php';

define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));

// Dapatkan redirect URI yang sesuai: jika tidak diisi di .env, deteksi domain yang sedang aktif saat ini
function getGoogleRedirectUri(): string {
    $configured = env('GOOGLE_REDIRECT_URI');
    if (!empty($configured)) {
        return $configured;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return $protocol . '://' . $host . ($baseDir ? $baseDir : '') . '/oauth_callback.php';
}

define('GOOGLE_REDIRECT_URI', getGoogleRedirectUri());

function getGoogleAuthUrl(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    // Simpan juga di cookie cadangan sebagai proteksi ganda jika session PHP terputus karena beda subdomain / proxy
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('oauth_state_backup', hash('sha256', $state), [
        'expires'  => time() + 600, // berlaku 10 menit
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}
