<?php
require_once __DIR__ . '/env.php';

$host     = env('DB_HOST', '127.0.0.1');
$port     = env('DB_PORT', '3306');
$dbname   = env('DB_NAME', 'db_dokumentasi');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Auto-migration: Otomatis tambahkan kolom avatar ke tabel users jika belum ada
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
        }
    } catch (Exception $ex) {
        // Abaikan jika pengguna database tidak memiliki permission ALTER
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
