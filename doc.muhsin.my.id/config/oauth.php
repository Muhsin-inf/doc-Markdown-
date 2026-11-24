<?php
require_once __DIR__ . '/env.php';

define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI', 'https://su.muhsin.my.id/oauth_callback.php'));

function getGoogleAuthUrl(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

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
