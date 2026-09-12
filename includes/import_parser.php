<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;


/**
 * ================================================================
 * READER XLS KHUSUS FILE FINGERSPOT
 * ================================================================
 *
 * Beberapa file XLS hasil export mesin Fingerspot memiliki
 * Summary Information / metadata yang menyebabkan
 * PhpSpreadsheet 5.9.0 menghasilkan:
 *
 * "File appears to be corrupt"
 *
 * Padahal data spreadsheet sebenarnya masih bisa dibaca.
 *
 * Reader ini melewati metadata tersebut.
 */
class XlsReaderTanpaMetadata extends Xls
{
    /**
     * Lewati Summary Information.
     */
    protected function readSummaryInformation(): void
    {
        // Sengaja dikosongkan.
    }

    /**
     * Lewati Document Summary Information.
     */
    protected function readDocumentSummaryInformation(): void
    {
        // Sengaja dikosongkan.
    }
}


/**
 * ================================================================
 * KONVERSI NOMOR KOLOM EXCEL KE HURUF
 * ================================================================
 *
 * Contoh:
 *
 * 1  = A
 * 2  = B
 * 26 = Z
 * 27 = AA
 * 28 = AB
 */
function kolom_ke_huruf(int $col): string
{
    $s = '';

    while ($col > 0) {

        $mod = ($col - 1) % 26;

        $s = chr(65 + $mod) . $s;

        $col = intdiv($col - $mod, 26) - 1;
    }

    return $s;
}


/**
 * ================================================================
 * PARSE EXCEL ABSENSI
 * ================================================================
 *
 * Membaca file Excel hasil export mesin Fingerspot.
 *
 * Sheet yang digunakan:
 *
 * - Ringkasan
 * - Catatan
 *
 * Struktur data yang dikembalikan:
 *
 * [
 *     'periode_awal'  => 'Y-m-d',
 *     'periode_akhir' => 'Y-m-d',
 *     'employees'     => [...],
 *     'scans'         => [...]
 * ]
 *
 * @throws Exception
 */
function parse_excel_absensi(string $filepath): array
{
    // ============================================================
    // 1. CEK FILE
    // ============================================================

    if (!file_exists($filepath)) {

        throw new \Exception(
            'File Excel tidak ditemukan.'
        );
    }


    if (!is_readable($filepath)) {

        throw new \Exception(
            'File Excel tidak dapat dibaca oleh sistem.'
        );
    }


    // ============================================================
    // 2. TENTUKAN JENIS FILE
    // ============================================================

    $extension = strtolower(
        pathinfo($filepath, PATHINFO_EXTENSION)
    );


    // ============================================================
    // 3. BACA FILE EXCEL
    // ============================================================

    try {

        /*
         * ========================================================
         * FILE XLS
         * ========================================================
         *
         * Gunakan reader khusus Fingerspot.
         */
        if ($extension === 'xls') {

            $reader = new XlsReaderTanpaMetadata();

            /*
             * Hanya membaca data.
             *
             * Tidak perlu style/format sehingga lebih ringan.
             */
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($filepath);
        }


        /*
         * ========================================================
         * FILE XLSX
         * ========================================================
         *
         * XLSX menggunakan reader normal.
         */
        elseif ($extension === 'xlsx') {

            $reader = IOFactory::createReader('Xlsx');

            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($filepath);
        }


        /*
         * ========================================================
         * FORMAT TIDAK DIDUKUNG
         * ========================================================
         */
        else {

            throw new \Exception(
                'Format file tidak didukung. ' .
                'Gunakan file .xls atau .xlsx.'
            );
        }

    } catch (\Throwable $e) {

        /*
         * Bungkus error supaya import.php mendapatkan
         * pesan yang lebih jelas.
         */
        throw new \Exception(
            'Gagal membaca file Excel: ' .
            $e->getMessage(),
            0,
            $e
        );
    }


    // ============================================================
    // 4. AMBIL SHEET RINGKASAN DAN CATATAN
    // ============================================================

    $ringkasan = $spreadsheet
        ->getSheetByName('Ringkasan');

    $catatan = $spreadsheet
        ->getSheetByName('Catatan');


    /*
     * Pastikan kedua sheet tersedia.
     */
    if (!$ringkasan || !$catatan) {

        throw new \Exception(
            'File ini tidak dikenali sebagai laporan absensi Fingerspot. ' .
            'Sheet "Ringkasan" dan/atau "Catatan" tidak ditemukan.'
        );
    }


    // ============================================================
    // 5. AMBIL PERIODE DARI CATATAN!C2
    // ============================================================

    /*
     * Contoh isi:
     *
     * 01/07/2026 ~ 31/07/2026
     *
     * atau:
     *
     * 01/07/2026~31/07/2026
     *
     * atau:
     *
     * 01/07/2026 - 31/07/2026
     */
    $periodeText = (string) $catatan
        ->getCell('C2')
        ->getValue();

    $periodeText = trim($periodeText);


    // ============================================================
    // 6. PARSING TANGGAL PERIODE
    // ============================================================

    if (
        !preg_match(
            '/(\d{2}\/\d{2}\/\d{4})\s*(?:~|-)\s*(\d{2}\/\d{2}\/\d{4})/',
            $periodeText,
            $m
        )
    ) {

        throw new \Exception(
            'Format periode tidak dikenali pada file Excel. ' .
            'Isi Catatan!C2: ' .
            $periodeText
        );
    }


    // ============================================================
    // 7. KONVERSI TANGGAL
    // ============================================================

    $awal = \DateTime::createFromFormat(
        'd/m/Y',
        $m[1]
    );

    $akhir = \DateTime::createFromFormat(
        'd/m/Y',
        $m[2]
    );


    /*
     * Pastikan tanggal valid.
     */
    if (!$awal || !$akhir) {

        throw new \Exception(
            'Tanggal periode pada file Excel tidak valid.'
        );
    }


    // ============================================================
    // 8. VALIDASI PERIODE
    // ============================================================

    if ($awal > $akhir) {

        throw new \Exception(
            'Tanggal awal periode lebih besar daripada tanggal akhir.'
        );
    }


    // ============================================================
    // 9. HITUNG JUMLAH HARI
    // ============================================================

    /*
     * Contoh:
     *
     * 01/07/2026 sampai 31/07/2026
     *
     * = 31 hari
     */
    $jumlah_hari = (int) $awal
        ->diff($akhir)
        ->days + 1;


    // ============================================================
    // 10. SIAPKAN ARRAY HASIL
    // ============================================================

    $employees = [];

    $scans = [];


    // ============================================================
    // 11. BARIS AWAL DATA KARYAWAN
    // ============================================================

    /*
     * Berdasarkan struktur file Fingerspot:
     *
     * Baris 5 = data karyawan pertama.
     */
    $row = 5;


    // ============================================================
    // 12. TENTUKAN BARIS TERAKHIR
    // ============================================================

    $maxRows = max(
        $ringkasan->getHighestRow(),
        $catatan->getHighestRow()
    );


    // ============================================================
    // 13. LOOP DATA KARYAWAN
    // ============================================================

    while ($row <= $maxRows) {


        // ========================================================
        // KOLOM A = ID / NOMOR MESIN
        // ========================================================

        $no = $ringkasan
            ->getCell('A' . $row)
            ->getValue();


        /*
         * Jika kosong, lanjut ke baris berikutnya.
         */
        if (
            $no === null ||
            trim((string) $no) === ''
        ) {

            $row++;

            continue;
        }


        // ========================================================
        // NORMALISASI ID
        // ========================================================

        /*
         * Contoh:
         *
         * Excel:
         * 1.0
         *
         * Menjadi:
         * "1"
         */
        if (
            is_float($no) &&
            floor($no) == $no
        ) {

            $no = (string) (int) $no;

        } else {

            $no = trim((string) $no);
        }


        // ========================================================
        // KOLOM B = NAMA
        // ========================================================

        $nama = trim(
            (string) $ringkasan
                ->getCell('B' . $row)
                ->getValue()
        );


        // ========================================================
        // KOLOM C = DEPARTEMEN
        // ========================================================

        $dept = trim(
            (string) $ringkasan
                ->getCell('C' . $row)
                ->getValue()
        );


        // ========================================================
        // SIMPAN DATA KARYAWAN
        // ========================================================

        $employees[$no] = [
            'no'   => $no,
            'nama' => $nama,
            'dept' => $dept,
        ];


        // ========================================================
        // 14. BACA SCAN SETIAP HARI
        // ========================================================

        /*
         * Pada sheet Catatan:
         *
         * D = hari pertama
         * E = hari kedua
         * F = hari ketiga
         * dst.
         *
         * Kolom A = ID
         * Kolom B = Nama
         * Kolom C = Departemen
         */

        for (
            $d = 1;
            $d <= $jumlah_hari;
            $d++
        ) {


            // ====================================================
            // TENTUKAN KOLOM HARI
            // ====================================================

            /*
             * Hari pertama:
             *
             * D = 4
             *
             * Karena:
             *
             * A = 1
             * B = 2
             * C = 3
             * D = 4
             */

            $colLetter = kolom_ke_huruf(
                3 + $d
            );


            // ====================================================
            // AMBIL DATA SCAN
            // ====================================================

            $val = $catatan
                ->getCell(
                    $colLetter . $row
                )
                ->getValue();


            // ====================================================
            // JIKA KOSONG
            // ====================================================

            if (
                $val === null ||
                trim((string) $val) === ''
            ) {

                continue;
            }


            // ====================================================
            // EKSTRAK JAM
            // ====================================================

            /*
             * Fungsi ekstrak_jam()
             * SUDAH ADA di:
             *
             * includes/functions.php
             *
             * Jadi TIDAK dibuat ulang di sini.
             */
            $jam_list = ekstrak_jam(
                (string) $val
            );


            /*
             * Jika tidak menemukan jam,
             * lanjut ke hari berikutnya.
             */
            if (empty($jam_list)) {

                continue;
            }


            // ====================================================
            // HITUNG TANGGAL
            // ====================================================

            /*
             * Contoh periode:
             *
             * 01/07/2026
             *
             * d = 1
             * → 01/07/2026
             *
             * d = 2
             * → 02/07/2026
             *
             * d = 3
             * → 03/07/2026
             */

            $tanggal = clone $awal;

            $tanggal->modify(
                '+' . ($d - 1) . ' days'
            );

            $tanggalKey = $tanggal
                ->format('Y-m-d');


            // ====================================================
            // SIMPAN DATA SCAN
            // ====================================================

            $scans[$no][$tanggalKey] = [
                'jam' => $jam_list,
                'jumlah_scan' => count($jam_list),
            ];
        }


        // ========================================================
        // LANJUT KE KARYAWAN BERIKUTNYA
        // ========================================================

        $row++;
    }


    // ============================================================
    // 15. VALIDASI DATA KARYAWAN
    // ============================================================

    if (empty($employees)) {

        throw new \Exception(
            'Tidak ada data karyawan yang ditemukan ' .
            'pada sheet "Ringkasan".'
        );
    }


    // ============================================================
    // 16. VALIDASI DATA SCAN
    // ============================================================

    if (empty($scans)) {

        throw new \Exception(
            'Data karyawan ditemukan, tetapi tidak ada data scan ' .
            'yang berhasil dibaca dari sheet "Catatan".'
        );
    }


    // ============================================================
    // 17. KEMBALIKAN HASIL
    // ============================================================

    return [

        'periode_awal' => $awal
            ->format('Y-m-d'),

        'periode_akhir' => $akhir
            ->format('Y-m-d'),

        'employees' => $employees,

        'scans' => $scans,
    ];
}