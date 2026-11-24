<?php
// config/env.php
// Lightweight, zero-dependency .env loader compatible with aaPanel & restricted hosting

if (!function_exists('loadEnv')) {
    function loadEnv(string $envPath): void {
        if (!file_exists($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            // Lewati baris komentar
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Pisahkan key dan value berdasarkan tanda '=' pertama
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Hapus tanda kutip jika ada (quotes)
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            // Tangani variabel boolean & null
            $lower = strtolower($val);
            if ($lower === 'true') {
                $val = true;
            } elseif ($lower === 'false') {
                $val = false;
            } elseif ($lower === 'null') {
                $val = null;
            }

            // Simpan ke $_ENV dan $_SERVER (aman dari disable_functions aaPanel)
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;

            // Panggil putenv hanya jika diizinkan oleh server
            if (function_exists('putenv')) {
                @putenv("{$key}={$val}");
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        if (function_exists('getenv')) {
            $val = @getenv($key);
            if ($val !== false && $val !== null) {
                return $val;
            }
        }
        return $default;
    }
}

// Cari file .env di direktori aplikasi saat ini atau direktori induk
$possibleEnvPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    dirname(__DIR__) . '/.env'
];

foreach ($possibleEnvPaths as $path) {
    if (file_exists($path)) {
        loadEnv($path);
        break;
    }
}
