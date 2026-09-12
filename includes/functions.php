<?php

/**
 * Escape output supaya aman dari XSS.
 */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect lalu hentikan eksekusi.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Simpan pesan flash.
 */
function flash(string $type, string $pesan): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'pesan' => $pesan
    ];
}

/**
 * Ambil & hapus pesan flash yang tersimpan.
 */
function ambil_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $f;
    }

    return null;
}

/**
 * Format tanggal Y-m-d menjadi format Indonesia.
 *
 * Contoh:
 * Senin, 06 Sep 2026
 */
function tgl_indo(?string $tanggal, bool $dengan_hari = true): string
{
    if (!$tanggal) {
        return '-';
    }

    $hari_list = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        "Jum'at",
        'Sabtu'
    ];

    $bulan_list = [
        '',
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];

    $ts = strtotime($tanggal);

    if ($ts === false) {
        return $tanggal;
    }

    $out = '';

    if ($dengan_hari) {
        $out .= $hari_list[(int) date('w', $ts)] . ', ';
    }

    $out .=
        date('d', $ts) .
        ' ' .
        $bulan_list[(int) date('n', $ts)] .
        ' ' .
        date('Y', $ts);

    return $out;
}

/**
 * Format jam H:i:s menjadi H:i.
 * Tampilkan '-' kalau kosong.
 */
function jam(?string $waktu): string
{
    if (!$waktu) {
        return '-';
    }

    return substr($waktu, 0, 5);
}

/**
 * Render badge status kehadiran.
 */
function badge_status(?string $status): string
{
    $map = [
        'hadir'  => ['success', 'Hadir'],
        'telat'  => ['warning', 'Telat'],
        'alpha'  => ['danger', 'Alpha'],
        'lembur' => ['primary', 'Lembur'],
        'izin'   => ['neutral', 'Izin'],
    ];

    [$warna, $label] =
        $map[$status] ??
        ['neutral', $status ?: '-'];

    return
        '<span class="badge badge--soft badge--' .
        $warna .
        '">' .
        e($label) .
        '</span>';
}

/**
 * Nama hari dalam angka.
 *
 * 1 = Senin
 * 2 = Selasa
 * ...
 * 7 = Minggu
 */
function hari_ke(string $tanggal): int
{
    return (int) date(
        'N',
        strtotime($tanggal)
    );
}

/**
 * Cek apakah shift berlaku pada tanggal tertentu.
 *
 * Contoh:
 * "1,2,3,4,5"
 */
function shift_berlaku_hari(
    string $hari_kerja_csv,
    string $tanggal
): bool {
    $hari = hari_ke($tanggal);

    $list = array_map(
        'trim',
        explode(',', $hari_kerja_csv)
    );

    return in_array(
        (string) $hari,
        $list,
        true
    );
}

/**
 * ============================================================
 * HITUNG STATUS KEHADIRAN
 * ============================================================
 *
 * Aturan:
 *
 * Shift masuk  : 08:00
 * Toleransi    : 10 menit
 *
 * Batas toleransi = 08:10
 *
 * 08:00 -> Hadir
 * 08:01 -> Hadir
 * 08:05 -> Hadir
 * 08:10 -> Hadir
 * 08:11 -> Telat 1 menit
 * 08:12 -> Telat 2 menit
 * 08:13 -> Telat 3 menit
 * 08:20 -> Telat 10 menit
 *
 * Mengembalikan:
 *
 * [
 *     status,
 *     menit_telat
 * ]
 */
function hitung_status(
    ?string $jam_masuk_aktual,
    string $jam_masuk_shift,
    int $toleransi_menit
): array {

    /*
     * ----------------------------------------------------------
     * 1. Tidak ada jam masuk
     * ----------------------------------------------------------
     */

    if (
        $jam_masuk_aktual === null ||
        trim($jam_masuk_aktual) === ''
    ) {
        return [
            'alpha',
            0
        ];
    }


    /*
     * ----------------------------------------------------------
     * 2. Bersihkan format jam aktual
     * ----------------------------------------------------------
     *
     * Mendukung:
     *
     * 08:13
     * 08:13:00
     * "08:13"
     *
     */

    $jam_masuk_aktual = trim(
        $jam_masuk_aktual
    );


    /*
     * ----------------------------------------------------------
     * 3. Bersihkan format jam shift
     * ----------------------------------------------------------
     */

    $jam_masuk_shift = trim(
        $jam_masuk_shift
    );


    /*
     * ----------------------------------------------------------
     * 4. Ambil HH dan MM dari jam aktual
     * ----------------------------------------------------------
     */

    if (
        !preg_match(
            '/^(\d{1,2}):(\d{2})/',
            $jam_masuk_aktual,
            $match_aktual
        )
    ) {
        /*
         * Kalau format jam tidak dikenali,
         * jangan membuat sistem error.
         */
        return [
            'hadir',
            0
        ];
    }


    /*
     * ----------------------------------------------------------
     * 5. Ambil HH dan MM dari jam shift
     * ----------------------------------------------------------
     */

    if (
        !preg_match(
            '/^(\d{1,2}):(\d{2})/',
            $jam_masuk_shift,
            $match_shift
        )
    ) {
        return [
            'hadir',
            0
        ];
    }


    /*
     * ----------------------------------------------------------
     * 6. Konversi jam aktual ke total menit
     * ----------------------------------------------------------
     */

    $jam_aktual = (int) $match_aktual[1];
    $menit_aktual = (int) $match_aktual[2];

    $total_menit_aktual =
        ($jam_aktual * 60) +
        $menit_aktual;


    /*
     * ----------------------------------------------------------
     * 7. Konversi jam shift ke total menit
     * ----------------------------------------------------------
     */

    $jam_shift = (int) $match_shift[1];
    $menit_shift = (int) $match_shift[2];

    $total_menit_shift =
        ($jam_shift * 60) +
        $menit_shift;


    /*
     * ----------------------------------------------------------
     * 8. Pastikan toleransi tidak negatif
     * ----------------------------------------------------------
     */

    $toleransi_menit = max(
        0,
        $toleransi_menit
    );


    /*
     * ----------------------------------------------------------
     * 9. Hitung batas keterlambatan
     * ----------------------------------------------------------
     *
     * Contoh:
     *
     * Shift     = 08:00
     * Toleransi = 10
     *
     * 08:00 + 10 menit
     * = 08:10
     */

    $batas_menit =
        $total_menit_shift +
        $toleransi_menit;


    /*
     * ----------------------------------------------------------
     * 10. Bandingkan jam masuk dengan batas toleransi
     * ----------------------------------------------------------
     */

    if (
        $total_menit_aktual <=
        $batas_menit
    ) {
        /*
         * Masih dalam batas toleransi.
         */
        return [
            'hadir',
            0
        ];
    }


    /*
     * ----------------------------------------------------------
     * 11. Karyawan terlambat
     * ----------------------------------------------------------
     *
     * Contoh:
     *
     * Shift     = 08:00
     * Toleransi = 10 menit
     * Batas     = 08:10
     *
     * Masuk     = 08:13
     *
     * 08:13 - 08:10
     * = 3 menit terlambat
     */

    $menit_telat =
        $total_menit_aktual -
        $batas_menit;


    return [
        'telat',
        $menit_telat
    ];
}

/**
 * Nama bulan Indonesia dari angka 1-12.
 */
function nama_bulan(int $bulan): string
{
    $list = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    return $list[$bulan] ?? '-';
}

/**
 * Ekstrak semua token jam HH:MM dari teks bebas.
 *
 * Contoh:
 *
 * "08:13 16:10"
 *
 * menjadi:
 *
 * [
 *     "08:13",
 *     "16:10"
 * ]
 */
function ekstrak_jam(string $teks): array
{
    preg_match_all(
        '/\d{2}:\d{2}/',
        $teks,
        $m
    );

    $jam = $m[0];

    sort($jam);

    return $jam;
}

/**
 * ============================================================
 * TENTUKAN JAM MASUK & JAM PULANG
 * ============================================================
 *
 * Aturan:
 *
 * 2 atau lebih scan:
 * - scan paling awal  = jam masuk
 * - scan paling akhir  = jam pulang
 *
 * 1 scan:
 * - dibandingkan dengan jam shift
 * - ditandai perlu_tinjau = true
 */
function tebak_masuk_pulang(
    array $jam_list,
    ?string $shift_masuk,
    ?string $shift_pulang
): array {

    /*
     * Normalisasi array.
     */
    $jam_list = array_values(
        $jam_list
    );

    /*
     * Urutkan jam.
     */
    sort($jam_list);

    /*
     * Jumlah scan.
     */
    $n = count($jam_list);


    /*
     * ----------------------------------------------------------
     * Tidak ada scan
     * ----------------------------------------------------------
     */

    if ($n === 0) {
        return [
            null,
            null,
            false
        ];
    }


    /*
     * ----------------------------------------------------------
     * 2 atau lebih scan
     * ----------------------------------------------------------
     */

    if ($n >= 2) {
        return [
            $jam_list[0],
            $jam_list[$n - 1],
            false
        ];
    }


    /*
     * ----------------------------------------------------------
     * Hanya 1 scan
     * ----------------------------------------------------------
     */

    $satu = $jam_list[0];


    /*
     * Kalau karyawan punya shift,
     * tentukan apakah scan tersebut lebih dekat
     * ke jam masuk atau jam pulang.
     */

    if (
        $shift_masuk &&
        $shift_pulang
    ) {

        $t = strtotime($satu);

        $jarak_masuk =
            abs(
                $t -
                strtotime($shift_masuk)
            );

        $jarak_pulang =
            abs(
                $t -
                strtotime($shift_pulang)
            );


        /*
         * Penanganan shift malam.
         *
         * Contoh:
         *
         * Masuk 22:00
         * Pulang 06:00
         */
        if (
            strtotime($shift_pulang) <
            strtotime($shift_masuk)
        ) {

            $jarak_pulang = min(
                $jarak_pulang,
                abs(
                    $t -
                    (
                        strtotime($shift_pulang) +
                        86400
                    )
                )
            );
        }


        /*
         * Lebih dekat ke jam masuk.
         */
        if (
            $jarak_masuk <=
            $jarak_pulang
        ) {
            return [
                $satu,
                null,
                true
            ];
        }


        /*
         * Lebih dekat ke jam pulang.
         */
        return [
            null,
            $satu,
            true
        ];
    }


    /*
     * Kalau tidak punya shift,
     * anggap satu scan sebagai jam masuk.
     */
    return [
        $satu,
        null,
        true
    ];
}