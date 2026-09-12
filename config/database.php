<?php
/**
 * Konfigurasi koneksi database.
 * Sesuaikan 4 baris di bawah ini dengan pengaturan MySQL di server kamu.
 * Kalau pakai XAMPP/Laragon default, biasanya cukup ganti DB_NAME saja.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'absensi_jafran');
define('DB_USER', 'root');
define('DB_PASS', '');

// Untuk keperluan development/testing lokal di lingkungan tanpa MySQL,
// set env APP_DB_DRIVER=sqlite dan APP_DB_PATH=/path/ke/file.sqlite
// Di server produksi (Windows/XAMPP), biarkan default (mysql).
$driver = getenv('APP_DB_DRIVER') ?: 'mysql';

try {
    if ($driver === 'sqlite') {
        $path = getenv('APP_DB_PATH') ?: (__DIR__ . '/../database/absensi.sqlite');
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Koneksi database gagal. Cek pengaturan di config/database.php. Detail: ' . htmlspecialchars($e->getMessage()));
}
