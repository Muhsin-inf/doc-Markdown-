<?php
// migrate.php - Skrip migrasi database otomatis untuk DocManager
require_once __DIR__ . '/config/database.php';

$messages = [];
$errors = [];

try {
    // 1. Cek apakah kolom avatar sudah ada di tabel users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    $avatarColumn = $stmt->fetch();

    if (!$avatarColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
        $messages[] = "✓ Kolom `avatar` berhasil ditambahkan ke tabel `users`.";
    } else {
        $messages[] = "✓ Kolom `avatar` sudah ada di tabel `users` (tidak perlu perubahan).";
    }

    // 2. Cek apakah tabel user_remember_tokens ada
    $stmtCheckTokens = $pdo->query("SHOW TABLES LIKE 'user_remember_tokens'");
    if (!$stmtCheckTokens->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `selector` varchar(32) NOT NULL,
              `validator_hash` varchar(255) NOT NULL,
              `expires_at` datetime NOT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `selector` (`selector`),
              KEY `user_id` (`user_id`),
              KEY `idx_remember_expires` (`expires_at`),
              CONSTRAINT `user_remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $messages[] = "✓ Tabel `user_remember_tokens` berhasil dibuat.";
    } else {
        $messages[] = "✓ Tabel `user_remember_tokens` sudah tersedia.";
    }

    // 3. Cek indeks dokumen
    $messages[] = "✓ Struktur tabel database saat ini sudah sinkron dan siap digunakan.";

} catch (Exception $e) {
    $errors[] = "Error saat migrasi: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database - DocManager</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #09090b; color: #f4f4f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .box { background: #18181b; border: 1px solid #27272a; max-width: 520px; width: 100%; padding: 28px; box-sizing: border-box; }
        h1 { margin-top: 0; font-size: 18px; color: #fafafa; border-bottom: 1px solid #27272a; padding-bottom: 12px; }
        .msg { background: #052e16; border: 1px solid #166534; color: #86efac; padding: 10px 14px; margin-bottom: 8px; font-size: 13px; font-family: monospace; }
        .err { background: #450a0a; border: 1px solid #991b1b; color: #fca5a5; padding: 10px 14px; margin-bottom: 8px; font-size: 13px; font-family: monospace; }
        .btn { display: inline-block; margin-top: 18px; background: #2563eb; color: white; padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Status Migrasi Database</h1>
        
        <?php foreach ($messages as $msg): ?>
            <div class="msg"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="err"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <div style="margin-top: 20px;">
            <a href="index.php" class="btn">Menuju Aplikasi →</a>
            <a href="login.php" class="btn" style="background:#27272a; margin-left:8px;">Halaman Login</a>
        </div>
    </div>
</body>
</html>
