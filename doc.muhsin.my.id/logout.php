<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) === 2 && ctype_xdigit($parts[0])) {
        $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
        $stmt->execute([$parts[0]]);
    }
    clearRememberCookie();
}

$_SESSION = [];
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();

header("Location: login.php");
exit;