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
 * Contoh: Senin, 06 Sep 2026
 */
function tgl_indo(?string $tanggal, bool $dengan_hari = true): string
{
    if (!$tanggal) {
        return '-';
    }

    $hari_list = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];

    $bulan_list = [
        '', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];

    $ts = strtotime($tanggal);

    if ($ts === false) {
        return $tanggal;
    }

    $out = '';

    if ($dengan_hari) {
        $out .= $hari_list[(int) date('w', $ts)] . ', ';
    }

    $out .= date('d', $ts) . ' ' . $bulan_list[(int) date('n', $ts)] . ' ' . date('Y', $ts);

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

    [$warna, $label] = $map[$status] ?? ['neutral', $status ?: '-'];

    return '<span class="badge badge--soft badge--' . $warna . '">' . e($label) . '</span>';
}

/**
 * Nama hari dalam angka.
 *
 * 1 = Senin ... 7 = Minggu
 */
function hari_ke(string $tanggal): int
{
    return (int) date('N', strtotime($tanggal));
}

/**
 * Cek apakah shift berlaku pada tanggal tertentu.
 *
 * Contoh hari_kerja: "1,2,3,4,5"
 */
function shift_berlaku_hari(string $hari_kerja_csv, string $tanggal): bool
{
    $hari = hari_ke($tanggal);

    $list = array_map('trim', explode(',', $hari_kerja_csv));

    return in_array((string) $hari, $list, true);
}

/**
 * ============================================================
 * HITUNG STATUS KEHADIRAN
 * ============================================================
 *
 * Contoh: shift masuk 08:00, toleransi 10 menit -> batas 08:10
 *
 * 08:10 -> Hadir
 * 08:11 -> Telat 1 menit
 *
 * Mengembalikan: [status, menit_telat]
 */
function hitung_status(
    ?string $jam_masuk_aktual,
    string $jam_masuk_shift,
    int $toleransi_menit
): array {

    if ($jam_masuk_aktual === null || trim($jam_masuk_aktual) === '') {
        return ['alpha', 0];
    }

    $jam_masuk_aktual = trim($jam_masuk_aktual);
    $jam_masuk_shift = trim($jam_masuk_shift);

    if (!preg_match('/^(\d{1,2}):(\d{2})/', $jam_masuk_aktual, $match_aktual)) {
        return ['hadir', 0];
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})/', $jam_masuk_shift, $match_shift)) {
        return ['hadir', 0];
    }

    $total_menit_aktual = ((int) $match_aktual[1] * 60) + (int) $match_aktual[2];
    $total_menit_shift = ((int) $match_shift[1] * 60) + (int) $match_shift[2];

    $toleransi_menit = max(0, $toleransi_menit);

    $batas_menit = $total_menit_shift + $toleransi_menit;

    if ($total_menit_aktual <= $batas_menit) {
        return ['hadir', 0];
    }

    return ['telat', $total_menit_aktual - $batas_menit];
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
 * Kalau tidak ada shift yang cocok, atau ada 2+ shift yang jaraknya
 * berdekatan (gray zone), fungsi tetap mengembalikan tebakan terbaik
 * tapi menandai perlu_tinjau = true.
 *
 * Mengembalikan: [shift_terpilih (array|null), perlu_tinjau (bool)]
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
        $overnight = $pulang <= $masuk;
        $akhir_jangkauan = $overnight ? $pulang + 1440 : $pulang;

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

        // Dedup berdasarkan jadwal (bukan baris shift): dua shift dengan
        // jam masuk, jam pulang, dan toleransi yang sama dianggap SATU
        // jadwal, supaya tidak selalu dianggap "ambigu".
        $signature = $masuk . '|' . $pulang . '|' . (int) $s['toleransi_menit'];

        if (
            !isset($kandidat_by_sig[$signature]) ||
            $jarak < $kandidat_by_sig[$signature]['jarak']
        ) {
            $kandidat_by_sig[$signature] = ['shift' => $s, 'jarak' => $jarak];
        }
    }

    $kandidat = array_values($kandidat_by_sig);

    // Tidak ada shift yang cocok -> ambil yang jam_masuk-nya terdekat
    // sebagai tebakan, tapi WAJIB ditinjau manual.
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

    // Ada 2+ kandidat dan jaraknya mepet (< 45 menit) -> zona abu-abu.
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
 * $ambang_menit dianggap bukan lembur.
 *
 * Butuh jam_masuk DAN jam_pulang shift supaya shift yang melewati
 * tengah malam (overnight) ditangani dengan benar.
 *
 * Mengembalikan JAM dalam bentuk desimal (2 angka di belakang koma).
 *
 * Contoh: Shift 08:00-16:00, pulang 20:30 -> 4.5 jam
 * Contoh overnight: Shift 20:00-06:00, pulang 23:37 -> 0 (pulang cepat)
 * Contoh overnight: Shift 20:00-06:00, pulang 07:30 -> 1.5 jam
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

    $overnight = $pulang_shift <= $masuk_shift;

    $pulang_shift_abs = $overnight ? $pulang_shift + 1440 : $pulang_shift;

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
 * ============================================================
 * PENGATURAN LEMBUR (dari tabel settings)
 * ============================================================
 *
 * - min_menit : lembur di bawah angka ini tidak dihitung
 * - max_jam   : batas maksimal lembur per hari (0 = tanpa batas)
 *
 * Kalau tabel settings belum ada / kosong, dipakai nilai default
 * supaya import tetap jalan.
 *
 * Mengembalikan: ['min_menit' => int, 'max_jam' => float]
 */
function ambil_pengaturan_lembur(PDO $pdo): array
{
    $hasil = [
        'min_menit' => 30,
        'max_jam' => 0.0,
    ];

    try {
        $rows = $pdo->query(
            "SELECT kunci, nilai FROM settings
             WHERE kunci IN ('lembur_min_menit', 'lembur_max_jam')"
        )->fetchAll();
    } catch (\Throwable $e) {
        return $hasil;
    }

    foreach ($rows as $r) {
        if ($r['kunci'] === 'lembur_min_menit') {
            $hasil['min_menit'] = max(0, (int) $r['nilai']);
        }

        if ($r['kunci'] === 'lembur_max_jam') {
            $hasil['max_jam'] = max(0.0, (float) $r['nilai']);
        }
    }

    return $hasil;
}

/**
 * Potong jam lembur kalau melebihi batas maksimal.
 * $maks_jam = 0 artinya tanpa batas.
 */
function batasi_lembur(float $jam_lembur, float $maks_jam): float
{
    if ($maks_jam > 0 && $jam_lembur > $maks_jam) {
        return round($maks_jam, 2);
    }

    return $jam_lembur;
}

/**
 * Nama bulan Indonesia dari angka 1-12.
 */
function nama_bulan(int $bulan): string
{
    $list = [
        '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    return $list[$bulan] ?? '-';
}

/**
 * Ekstrak semua token jam HH:MM dari teks bebas.
 *
 * "08:13 16:10" -> ["08:13", "16:10"]
 */
function ekstrak_jam(string $teks): array
{
    preg_match_all('/\d{2}:\d{2}/', $teks, $m);

    $jam = $m[0];

    sort($jam);

    return $jam;
}

/**
 * ============================================================
 * TENTUKAN JAM MASUK & JAM PULANG
 * ============================================================
 *
 * 2 atau lebih scan: scan paling awal = masuk, paling akhir = pulang.
 * 1 scan: ditandai perlu_tinjau = true.
 *
 * Kalau $shift_masuk / $shift_pulang dikirim null (kasus import
 * sekarang), 1 scan otomatis dianggap "jam masuk".
 *
 * Mengembalikan: [jam_masuk, jam_pulang, perlu_tinjau]
 */
function tebak_masuk_pulang(
    array $jam_list,
    ?string $shift_masuk,
    ?string $shift_pulang
): array {

    $jam_list = array_values($jam_list);

    sort($jam_list);

    $n = count($jam_list);

    if ($n === 0) {
        return [null, null, false];
    }

    if ($n >= 2) {
        return [$jam_list[0], $jam_list[$n - 1], false];
    }

    $satu = $jam_list[0];

    if ($shift_masuk && $shift_pulang) {

        $t = strtotime($satu);

        $jarak_masuk = abs($t - strtotime($shift_masuk));
        $jarak_pulang = abs($t - strtotime($shift_pulang));

        if (strtotime($shift_pulang) < strtotime($shift_masuk)) {
            $jarak_pulang = min(
                $jarak_pulang,
                abs($t - (strtotime($shift_pulang) + 86400))
            );
        }

        if ($jarak_masuk <= $jarak_pulang) {
            return [$satu, null, true];
        }

        return [null, $satu, true];
    }

    return [$satu, null, true];
}