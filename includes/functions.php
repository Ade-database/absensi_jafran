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
 * Konversi "HH:MM" atau "HH:MM:SS" menjadi total menit sejak 00:00.
 * Return null kalau formatnya tidak dikenali / kosong.
 */
function jam_ke_menit(?string $jam): ?int
{
    if ($jam === null || trim($jam) === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($jam), $m)) {
        return null;
    }

    return ((int) $m[1]) * 60 + (int) $m[2];
}

/**
 * ============================================================
 * DETEKSI SHIFT DARI JAM CHECK-IN
 * ============================================================
 *
 * Shift bukan properti tetap milik karyawan. Fungsi ini mencari
 * shift mana yang "jangkauannya" mencakup jam check-in aktual.
 *
 * Jangkauan sebuah shift = (jam_masuk - $grace_menit) s.d. jam_pulang,
 * dengan penanganan shift yang melewati tengah malam (overnight).
 *
 * Kalau tidak ada shift yang jangkauannya cocok sama sekali, atau
 * ada 2+ shift yang jaraknya ke jam check-in berdekatan (gray zone),
 * fungsi tetap mengembalikan tebakan terbaik tapi menandai
 * $perlu_tinjau = true supaya admin mengecek manual.
 *
 * Mengembalikan:
 *
 * [
 *     shift_terpilih (array|null),
 *     perlu_tinjau (bool)
 * ]
 */
function deteksi_shift(
    string $jam_check_in,
    array $shifts,
    int $grace_menit = 180
): array {

    $menit_check = jam_ke_menit($jam_check_in);

    if ($menit_check === null || empty($shifts)) {
        return [null, true];
    }

    $kandidat_by_sig = [];

    foreach ($shifts as $s) {

        $masuk = jam_ke_menit($s['jam_masuk']);
        $pulang = jam_ke_menit($s['jam_pulang']);

        if ($masuk === null || $pulang === null) {
            continue;
        }

        $mulai_jangkauan = $masuk - $grace_menit;
        $overnight = $pulang <= $masuk; // shift melewati tengah malam
        $akhir_jangkauan = $overnight ? $pulang + 1440 : $pulang;

        // Cek 3 representasi (hari sebelum/ini/sesudah) supaya jangkauan
        // yang mepet jam 00:00 tetap terdeteksi dengan benar.
        $cocok = false;

        foreach ([$menit_check - 1440, $menit_check, $menit_check + 1440] as $t) {
            if ($t >= $mulai_jangkauan && $t <= $akhir_jangkauan) {
                $cocok = true;
                break;
            }
        }

        if (!$cocok) {
            continue;
        }

        $jarak = min(
            abs($menit_check - $masuk),
            abs($menit_check - $masuk - 1440),
            abs($menit_check - $masuk + 1440)
        );

        // ------------------------------------------------------
        // PENTING: dedup berdasarkan jadwal, bukan berdasarkan
        // baris shift.
        // ------------------------------------------------------
        // Kalau ada 2+ shift dengan jam_masuk, jam_pulang, DAN
        // toleransi yang PERSIS SAMA (mis. "Shift Kantor" untuk
        // departemen A dan "Shift Processing Normal" untuk
        // departemen B, tapi jamnya sama-sama 08:00-16:00),
        // keduanya dianggap SATU jadwal yang sama — bukan dua
        // kandidat yang "bersaing" — karena hasil hitung telat &
        // lembur akan identik siapa pun yang dipilih. Tanpa dedup
        // ini, dua shift kembar akan SELALU dianggap "ambigu"
        // (jaraknya sama-sama 0), membuat hampir semua data
        // ditandai "Perlu Ditinjau" walau sebenarnya jelas.
        $signature = $masuk . '|' . $pulang . '|' . (int) $s['toleransi_menit'];

        if (
            !isset($kandidat_by_sig[$signature]) ||
            $jarak < $kandidat_by_sig[$signature]['jarak']
        ) {
            $kandidat_by_sig[$signature] = ['shift' => $s, 'jarak' => $jarak];
        }
    }

    $kandidat = array_values($kandidat_by_sig);

    // Tidak ada shift yang jangkauannya cocok -> ambil yang jam_masuk-nya
    // paling dekat sebagai tebakan, tapi WAJIB ditinjau manual.
    if (empty($kandidat)) {

        $terdekat = null;
        $jarak_terdekat = PHP_INT_MAX;

        foreach ($shifts as $s) {

            $masuk = jam_ke_menit($s['jam_masuk']);

            if ($masuk === null) {
                continue;
            }

            $jarak = min(
                abs($menit_check - $masuk),
                abs($menit_check - $masuk - 1440),
                abs($menit_check - $masuk + 1440)
            );

            if ($jarak < $jarak_terdekat) {
                $jarak_terdekat = $jarak;
                $terdekat = $s;
            }
        }

        return [$terdekat, true];
    }

    usort($kandidat, fn($a, $b) => $a['jarak'] <=> $b['jarak']);

    $ambigu = false;

    // Ada 2+ kandidat dan yang terbaik & kedua-terbaik jaraknya mepet
    // (< 45 menit) -> ini zona abu-abu, tandai untuk ditinjau.
    //
    // Catatan: ambang ini sengaja dibuat cukup ketat. Kalau di
    // database ada beberapa shift dengan jam mulai yang berdekatan
    // (mis. 07:00 dan 08:00), ambang yang longgar akan membuat
    // HAMPIR SEMUA check-in ditandai "perlu ditinjau" walau
    // sebenarnya jelas shift mana yang cocok.
    if (count($kandidat) > 1 && ($kandidat[1]['jarak'] - $kandidat[0]['jarak']) < 45) {
        $ambigu = true;
    }

    return [$kandidat[0]['shift'], $ambigu];
}

/**
 * ============================================================
 * HITUNG JAM LEMBUR
 * ============================================================
 *
 * Selisih jam pulang aktual vs jam selesai shift. Di bawah
 * $ambang_menit dianggap bukan lembur (cuma telat pulang wajar,
 * misalnya beres-beres sebentar).
 *
 * PENTING: fungsi ini butuh jam_masuk DAN jam_pulang shift
 * (bukan cuma jam_pulang) supaya bisa menangani shift yang
 * melewati tengah malam (overnight) dengan benar. Tanpa
 * jam_masuk sebagai acuan, sistem tidak bisa membedakan
 * "pulang jam 07:00 karena lembur semalaman" vs
 * "pulang jam 07:00 karena pulang cepat di sore/malam hari".
 *
 * Mengembalikan JAM dalam bentuk desimal, dibulatkan 2 angka
 * di belakang koma (kolom attendance_daily.jam_lembur bertipe
 * DECIMAL(4,2), bukan menit integer).
 *
 * Contoh (shift normal):
 * Shift 08:00-16:00, pulang aktual 20:30
 * Selisih = 270 menit -> 4.5 jam lembur
 *
 * Contoh (shift overnight, pulang LEBIH CEPAT dari jadwal):
 * Shift 20:00-06:00, pulang aktual 23:37 (masih malam yang sama)
 * -> BUKAN lembur, karyawan pulang cepat ~6 jam lebih awal.
 *
 * Contoh (shift overnight, pulang LEBIH LAMA dari jadwal):
 * Shift 20:00-06:00, pulang aktual 07:30 (sudah lewat tengah malam)
 * Selisih = 90 menit -> 1.5 jam lembur
 */
function hitung_lembur(
    ?string $jam_pulang_aktual,
    string $jam_masuk_shift,
    string $jam_pulang_shift,
    int $ambang_menit = 30
): float {

    $aktual = jam_ke_menit($jam_pulang_aktual);
    $masuk_shift = jam_ke_menit($jam_masuk_shift);
    $pulang_shift = jam_ke_menit($jam_pulang_shift);

    if ($aktual === null || $masuk_shift === null || $pulang_shift === null) {
        return 0.0;
    }

    // Shift overnight kalau jam pulang (dalam angka) <= jam masuk,
    // artinya jam pulang itu sebenarnya "hari berikutnya".
    $overnight = $pulang_shift <= $masuk_shift;

    // Posisikan jam selesai shift pada garis waktu absolut: kalau
    // overnight, jam selesainya dianggap ada di "hari berikutnya".
    $pulang_shift_abs = $overnight ? $pulang_shift + 1440 : $pulang_shift;

    // Posisikan jam pulang aktual pada garis waktu yang sama.
    //
    // Untuk shift overnight: kalau jam pulang aktual masih LEBIH
    // KECIL dari jam masuk shift, itu tandanya sudah lewat tengah
    // malam (berarti "hari berikutnya"). Kalau jam pulang aktual
    // masih LEBIH BESAR dari jam masuk shift, berarti masih di
    // malam yang sama (belum lewat tengah malam) — ini kasus
    // pulang cepat, bukan lembur.
    if ($overnight && $aktual < $masuk_shift) {
        $aktual_abs = $aktual + 1440;
    } else {
        $aktual_abs = $aktual;
    }

    $selisih_menit = $aktual_abs - $pulang_shift_abs;

    if ($selisih_menit < $ambang_menit) {
        return 0.0;
    }

    return round($selisih_menit / 60, 2);
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
 * - dibandingkan dengan jam shift (kalau ada)
 * - ditandai perlu_tinjau = true
 *
 * Catatan: sejak shift tidak lagi jadi properti tetap karyawan,
 * $shift_masuk / $shift_pulang biasanya dikirim null dari
 * pemanggil (import.php), sehingga 1 scan otomatis dianggap
 * "jam masuk" dan selalu ditandai perlu ditinjau.
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
     * Kalau ada shift tetap yang dikirim (kasus lama / manual),
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
     * Kalau tidak ada shift tetap dikirim (kasus baru: shift dideteksi
     * belakangan dari jam masuk), anggap satu scan sebagai jam masuk.
     */
    return [
        $satu,
        null,
        true
    ];
}