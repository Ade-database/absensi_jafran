<?php
require_once __DIR__ . '/config/database.php';

echo "Database yang dipakai PHP: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "<br>";
echo "Daftar tabel yang terlihat:<br>";
$tabel = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tabel as $t) {
    echo "- $t<br>";
}