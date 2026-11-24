-- ========================================================
-- Migrasi Database: Menambahkan Kolom avatar ke Tabel users
-- Jalankan query ini di phpMyAdmin atau aaPanel MySQL console
-- ========================================================

ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) NULL AFTER `email`;
