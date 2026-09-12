<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rats\Zkteco\Lib\ZKTeco;

$ip_mesin = '192.168.10.71';
$port_mesin = 4370;

echo "<h2>Test Koneksi Mesin Fingerspot</h2>";
echo "IP Mesin : <b>{$ip_mesin}</b><br>";
echo "Port     : <b>{$port_mesin}</b><br><br>";

try {

    echo "Mencoba koneksi ke mesin...<br><br>";

    $zk = new ZKTeco($ip_mesin, $port_mesin);

    if ($zk->connect()) {

        echo "<h2 style='color:green;'>✅ MESIN TERHUBUNG!</h2>";

        echo "<hr>";
        echo "<b>Info tambahan:</b><br><br>";

        // Coba ambil versi mesin
        try {
            $version = $zk->version();
            echo "Versi Mesin : <b>" . htmlspecialchars($version) . "</b><br>";
        } catch (\Throwable $e) {
            echo "Versi Mesin : <span style='color:red;'>Gagal dibaca</span><br>";
        }

        // Putuskan koneksi
        $zk->disconnect();

        echo "<br>";
        echo "<span style='color:green;'>✅ Koneksi berhasil dan sudah diputuskan dengan aman.</span>";

    } else {

        echo "<h2 style='color:red;'>❌ GAGAL TERHUBUNG!</h2>";

        echo "<p>";
        echo "Port 4370 memang terbuka, tetapi library <b>rats/zkteco</b> ";
        echo "belum tentu berhasil berkomunikasi dengan protokol mesin.";
        echo "</p>";
    }

} catch (\Throwable $e) {

    echo "<h2 style='color:red;'>❌ ERROR</h2>";

    echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";
}
?>