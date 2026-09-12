<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xls;

class XlsReaderTanpaMetadata extends Xls
{
    protected function readSummaryInformation(): void
    {
        // Lewati Summary Information
    }

    protected function readDocumentSummaryInformation(): void
    {
        // Lewati Document Summary Information
    }
}

$file = __DIR__ . '/storage/tmp/Absen 31-07-2026 (1).xls';

echo "<h3>Test Excel Tanpa Metadata</h3>";

if (!file_exists($file)) {
    die(
        "File tidak ditemukan:<br><br>" .
        htmlspecialchars($file)
    );
}

echo "File ditemukan ✅<br>";
echo "Ukuran file: " . filesize($file) . " bytes<br><br>";

try {

    $reader = new XlsReaderTanpaMetadata();

    $reader->setReadDataOnly(true);

    echo "Mencoba membaca XLS tanpa Summary Information...<br><br>";

    $spreadsheet = $reader->load($file);

    echo "<h3 style='color:green;'>BERHASIL DIBACA ✅</h3>";

    echo "<b>Sheet yang ditemukan:</b><br>";

    foreach ($spreadsheet->getSheetNames() as $sheet) {
        echo "- " . htmlspecialchars($sheet) . "<br>";
    }

    echo "<br>";

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {

        echo "<hr>";

        echo "<b>Sheet:</b> "
            . htmlspecialchars($worksheet->getTitle())
            . "<br>";

        echo "Highest Row: "
            . $worksheet->getHighestRow()
            . "<br>";

        echo "Highest Column: "
            . $worksheet->getHighestColumn()
            . "<br><br>";

        echo "<b>Contoh data:</b><br>";

        $data = $worksheet->toArray(
            null,
            true,
            true,
            true
        );

        $jumlah = 0;

        foreach ($data as $rowNumber => $row) {

            echo "Baris {$rowNumber}: ";

            foreach ($row as $column => $value) {

                if ($value !== null && $value !== '') {
                    echo htmlspecialchars($column . "=" . $value) . " | ";
                }
            }

            echo "<br>";

            $jumlah++;

            if ($jumlah >= 10) {
                break;
            }
        }
    }

} catch (\Throwable $e) {

    echo "<h3 style='color:red;'>MASIH GAGAL ❌</h3>";

    echo "<b>Class:</b><br>";
    echo "<pre>";
    echo htmlspecialchars(get_class($e));
    echo "</pre>";

    echo "<b>Message:</b><br>";
    echo "<pre>";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";

    echo "<b>File:</b><br>";
    echo "<pre>";
    echo htmlspecialchars($e->getFile());
    echo "</pre>";

    echo "<b>Line:</b><br>";
    echo "<pre>";
    echo htmlspecialchars((string) $e->getLine());
    echo "</pre>";

    echo "<b>Stack Trace:</b><br>";
    echo "<pre style='white-space:pre-wrap;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}