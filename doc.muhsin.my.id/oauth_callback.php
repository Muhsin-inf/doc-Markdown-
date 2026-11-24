<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/oauth.php';

if (empty($_GET['code']) || empty($_GET['state'])) {
    die("Login OAuth dibatalkan atau parameter tidak lengkap. <a href='login.php'>Kembali ke Login</a>");
}

if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    die("State OAuth tidak valid (potensi CSRF). <a href='login.php'>Kembali ke Login</a>");
}
unset($_SESSION['oauth_state']);

// 1. Tukar authorization code dengan access token
$tokenCurl = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($tokenCurl, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
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
    die("Token akses Google tidak diterima.");
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
} else {
    // Buat akun baru otomatis untuk pengguna Google
    $insert = $pdo->prepare("INSERT INTO users (name, email, password, oauth_provider, oauth_id, role) VALUES (?, ?, NULL, 'google', ?, 'user')");
    $insert->execute([$name, $email, $oauthId]);
    $userId = (int) $pdo->lastInsertId();
    $userName = $name;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $userName;

header("Location: index.php");
exit;