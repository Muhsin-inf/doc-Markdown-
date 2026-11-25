<?php
// config/auth.php

// ─── Persistent Session Configuration ─────────────────────────────────────────
// Seperti Google Drive: session tidak habis selama browser hidup,
// dan remember-me token otomatis di-refresh setiap kunjungan (rolling token).
// ──────────────────────────────────────────────────────────────────────────────

const REMEMBER_COOKIE_NAME   = 'docmgr_remember';
const REMEMBER_LIFETIME_DAYS = 365;          // token aktif 1 tahun jika tidak aktif
const SESSION_LIFETIME_SECS  = 86400 * 30;  // session PHP hidup 30 hari di server

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Perpanjang gc maxlifetime agar session di server tidak dihapus prematur
    @ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECS);

    // Cookie session hidup 30 hari (bukan 0 = sampai browser tutup)
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECS,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Perpanjang cookie setiap request agar tidak habis selama aktif (rolling)
    if (!empty($_SESSION['user_id'])) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + SESSION_LIFETIME_SECS,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function setRememberCookie(string $cookieValue, int $expiresTimestamp): void {
    setcookie(REMEMBER_COOKIE_NAME, $cookieValue, [
        'expires'  => $expiresTimestamp,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberCookie(): void {
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/**
 * Buat remember-me token baru (rolling: hapus lama, buat baru).
 * Dipanggil saat login DAN saat token lama digunakan (rolling refresh).
 */
function createRememberToken(PDO $pdo, int $userId): void {
    // Hapus token lama milik user ini yang sudah expired
    $cleanup = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND expires_at <= NOW()");
    $cleanup->execute([$userId]);

    $selector      = bin2hex(random_bytes(12));
    $validator     = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);

    $expiresTimestamp = time() + (REMEMBER_LIFETIME_DAYS * 86400);
    $expiresAtSql     = date('Y-m-d H:i:s', $expiresTimestamp);

    $stmt = $pdo->prepare("
        INSERT INTO user_remember_tokens (user_id, selector, validator_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $selector, $validatorHash, $expiresAtSql]);

    setRememberCookie($selector . ':' . $validator, $expiresTimestamp);
}

/**
 * Periksa cookie remember-me → login otomatis.
 * Setelah berhasil, langsung buat token baru (rolling).
 */
function checkRememberMeLogin(PDO $pdo): bool {
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2) {
        clearRememberCookie();
        return false;
    }

    [$selector, $validator] = $parts;
    if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
        clearRememberCookie();
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT rt.id, rt.user_id, rt.validator_hash, rt.expires_at, u.name
        FROM user_remember_tokens rt
        JOIN users u ON rt.user_id = u.id
        WHERE rt.selector = ? AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$selector]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        clearRememberCookie();
        return false;
    }

    $calculatedHash = hash('sha256', $validator);
    if (!hash_equals($tokenRow['validator_hash'], $calculatedHash)) {
        // Token tidak cocok — hapus semua token user ini (kemungkinan token dicuri)
        $del = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        $del->execute([$tokenRow['user_id']]);
        clearRememberCookie();
        return false;
    }

    // Token valid → regenerate session
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $tokenRow['user_id'];
    $_SESSION['user_name'] = $tokenRow['name'];

    // Hapus token lama & buat yang baru (rolling token — tidak akan expired selama aktif)
    $delOld = $pdo->prepare("DELETE FROM user_remember_tokens WHERE id = ?");
    $delOld->execute([$tokenRow['id']]);
    createRememberToken($pdo, (int) $tokenRow['user_id']);

    return true;
}

/**
 * Wajibkan login — cek session dulu, lalu remember-me cookie.
 * Selalu panggil dengan $pdo agar auto-login dari cookie bekerja.
 */
function requireLogin(?PDO $pdo = null): void {
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    if ($pdo instanceof PDO && checkRememberMeLogin($pdo)) {
        return;
    }

    header("Location: login.php");
    exit;
}

function createSlug(string $string): string {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    if ($slug === '') {
        $slug = 'dokumen';
    }
    return $slug . '-' . substr(uniqid(), -5);
}

function getCurrentUser(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, avatar, oauth_provider, oauth_id, role, created_at, (password IS NOT NULL AND password != '') AS has_password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? null;
            return $user;
        }
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT id, name, email, NULL AS avatar, oauth_provider, oauth_id, role, created_at, (password IS NOT NULL AND password != '') AS has_password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_avatar'] = null;
            return $user;
        }
    }
    return null;
}
