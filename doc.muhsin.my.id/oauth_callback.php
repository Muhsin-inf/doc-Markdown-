<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/oauth.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    die("Login OAuth dibatalkan atau parameter tidak lengkap. <a href='login.php'>Kembali ke Login</a>");
}

// Cek validasi state dari session PHP atau cookie backup
$sessionState = $_SESSION['oauth_state'] ?? '';
$backupStateHash = $_COOKIE['oauth_state_backup'] ?? '';

$isStateValid = false;
if (!empty($sessionState) && hash_equals($sessionState, $state)) {
    $isStateValid = true;
} elseif (!empty($backupStateHash) && hash_equals($backupStateHash, hash('sha256', $state))) {
    $isStateValid = true;
}

if (!$isStateValid) {
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>OAuth Error - DocManager</title>
        <style>
            body { font-family: system-ui, sans-serif; background: #09090b; color: #f4f4f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .box { background: #18181b; border: 1px solid #27272a; max-width: 500px; padding: 24px; box-sizing: border-box; }
            h2 { color: #f87171; margin-top: 0; font-size: 18px; }
            p { font-size: 13px; color: #a1a1aa; line-height: 1.5; }
            code { background: #27272a; color: #60a5fa; padding: 2px 6px; font-size: 12px; }
            .btn { display: inline-block; margin-top: 16px; background: #2563eb; color: white; padding: 8px 16px; text-decoration: none; font-size: 13px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>State OAuth Tidak Valid (Domain / Session Mismatch)</h2>
            <p>Sesi login terputus saat Google mengalihkan (redirect) kembali ke server.</p>
            <p><strong>Penyebab Utama:</strong> Domain tempat Anda login berbeda dengan Redirect URI Google.</p>
            <p>Domain saat ini: <code>https://<?= htmlspecialchars($currentHost) ?>/oauth_callback.php</code><br>
            Redirect URI terdaftar: <code><?= htmlspecialchars(GOOGLE_REDIRECT_URI) ?></code></p>
            <p style="margin-bottom:0;">Pastikan di <strong>Google Cloud Console</strong> & file <strong>.env</strong> Anda, Redirect URI sama persis dengan domain yang Anda gunakan saat ini.</p>
            <a href="login.php" class="btn">← Coba Login Kembali</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Bersihkan state setelah lolos validasi
unset($_SESSION['oauth_state']);
if (isset($_COOKIE['oauth_state_backup'])) {
    setcookie('oauth_state_backup', '', time() - 3600, '/');
}

// 1. Tukar authorization code dengan access token
$tokenCurl = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($tokenCurl, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15
]);
$tokenResponse = curl_exec($tokenCurl);
$tokenError = curl_error($tokenCurl);
curl_close($tokenCurl);

if ($tokenError || !$tokenResponse) {
    die("Gagal menghubungi server token Google.");
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';

if ($accessToken === '') {
    die("Token akses Google tidak diterima: " . htmlspecialchars($tokenResponse));
}

// 2. Ambil data profil user dari Google
$infoCurl = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($infoCurl, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15
]);
$infoResponse = curl_exec($infoCurl);
curl_close($infoCurl);

$googleUser = json_decode($infoResponse, true);
$oauthId = $googleUser['id'] ?? '';
$email = trim($googleUser['email'] ?? '');
$name = trim($googleUser['name'] ?? ($email ? explode('@', $email)[0] : 'Pengguna Google'));
$picture = trim($googleUser['picture'] ?? '');

if ($oauthId === '' || $email === '') {
    die("Data email atau ID Google tidak tersedia.");
}

// 3. Cek apakah user sudah ada berdasarkan oauth atau email
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE (oauth_provider = 'google' AND oauth_id = :oauth_id) 
       OR email = :email 
    LIMIT 1
");
$stmt->execute([
    'oauth_id' => $oauthId,
    'email'    => $email
]);
$user = $stmt->fetch();

if ($user) {
    // Jika akun lama pakai email/password, tautkan data Google OAuth-nya
    if (empty($user['oauth_id'])) {
        $update = $pdo->prepare("UPDATE users SET oauth_provider = 'google', oauth_id = ? WHERE id = ?");
        $update->execute([$oauthId, $user['id']]);
    }
    $userId = (int) $user['id'];
    $userName = $user['name'];
    $userAvatar = $user['avatar'] ?? null;
} else {
    // Buat akun baru otomatis untuk pengguna Google
    $insert = $pdo->prepare("INSERT INTO users (name, email, password, oauth_provider, oauth_id, role) VALUES (?, ?, NULL, 'google', ?, 'user')");
    $insert->execute([$name, $email, $oauthId]);
    $userId = (int) $pdo->lastInsertId();
    $userName = $name;
    $userAvatar = null;
}

session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['user_name'] = $userName;
$_SESSION['user_avatar'] = $userAvatar;

// Buat remember-me token otomatis setelah OAuth login (seperti Google Drive)
createRememberToken($pdo, $userId);

header("Location: index.php");
exit;
