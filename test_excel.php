<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xls;

$file = __DIR__ . '/storage/tmp/Absen 31-07-2026 (1).xls';

echo "<h3>Test Baca Excel</h3>";

if (!file_exists($file)) {
    die(
        "File tidak ditemukan:<br><br>" .
        htmlspecialchars($file)
    );
}

echo "File ditemukan ✅<br>";
echo "Ukuran file: " . filesize($file) . " bytes<br><br>";

try {

    $reader = new Xls();
    $reader->setReadDataOnly(true);

    echo "Mencoba membaca dengan PhpSpreadsheet Xls Reader...<br><br>";

    $spreadsheet = $reader->load($file);

    echo "<b style='color:green;'>BERHASIL DIBACA ✅</b><br><br>";

    echo "<b>Sheet:</b><br>";

    foreach ($spreadsheet->getSheetNames() as $sheet) {
        echo "- " . htmlspecialchars($sheet) . "<br>";
    }

} catch (\Throwable $e) {

    echo "<h3 style='color:red;'>GAGAL DIBACA ❌</h3>";

    echo "<h4>1. Class</h4>";
    echo "<pre>";
    var_dump(get_class($e));
    echo "</pre>";

    echo "<h4>2. Message</h4>";
    echo "<pre>";
    var_dump($e->getMessage());
    echo "</pre>";

    echo "<h4>3. Code</h4>";
    echo "<pre>";
    var_dump($e->getCode());
    echo "</pre>";

    echo "<h4>4. File</h4>";
    echo "<pre>";
    var_dump($e->getFile());
    echo "</pre>";

    echo "<h4>5. Line</h4>";
    echo "<pre>";
    var_dump($e->getLine());
    echo "</pre>";

    echo "<h4>6. Previous Exception</h4>";
    echo "<pre>";

    if ($e->getPrevious()) {
        var_dump([
            'class' => get_class($e->getPrevious()),
            'message' => $e->getPrevious()->getMessage(),
            'file' => $e->getPrevious()->getFile(),
            'line' => $e->getPrevious()->getLine(),
        ]);
    } else {
        echo "Tidak ada previous exception.";
    }

    echo "</pre>";

    echo "<h4>7. FULL STACK TRACE</h4>";
    echo "<pre style='white-space: pre-wrap;'>";
    echo $e->getTraceAsString();
    echo "</pre>";
}