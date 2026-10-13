<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
$departemen_id = $_GET['departemen_id'] ?? '';
$q = trim($_GET['q'] ?? '');

// Validasi bulan: harus 1-12, kalau tidak valid kembali ke bulan berjalan
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) date('n');
}

// Validasi tahun: batasi ke rentang wajar supaya sprintf/strtotime tidak
// menghasilkan tanggal aneh dari input sembarangan (mis. tahun=abc jadi 0,
// atau tahun=99999). Batas atas dilonggarkan +1 dari tahun berjalan untuk
// jaga-jaga penjadwalan lintas tahun.
$tahun_min = 2000;
$tahun_max = (int) date('Y') + 1;
if ($tahun < $tahun_min || $tahun > $tahun_max) {
    $tahun = (int) date('Y');
}

// Validasi departemen_id: kalau diisi tapi bukan angka, anggap "Semua"
if ($departemen_id !== '' && !ctype_digit((string) $departemen_id)) {
    $departemen_id = '';
}

$awal = sprintf('%04d-%02d-01', $tahun, $bulan);
$akhir = date('Y-m-t', strtotime($awal));

// Kolom lembur = TOTAL JAM lembur (SUM jam_lembur), bukan jumlah baris
// berstatus "lembur", karena lembur disimpan sebagai jam di kolom
// attendance_daily.jam_lembur.
$sql = "SELECT e.id, e.nik, e.nama, d.nama_departemen,
               SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS jumlah_hadir,
               SUM(CASE WHEN a.status = 'telat' THEN 1 ELSE 0 END) AS jumlah_telat,
               COALESCE(SUM(a.jam_lembur), 0) AS total_jam_lembur,
               COUNT(DISTINCT a.tanggal) AS hari_tercatat
        FROM employees e
        LEFT JOIN departments d ON d.id = e.departemen_id
        LEFT JOIN attendance_daily a ON a.employee_id = e.id AND a.tanggal BETWEEN ? AND ?
        WHERE e.status_aktif = 1";
$params = [$awal, $akhir];

if ($departemen_id !== '') {
    $sql .= ' AND e.departemen_id = ?';
    $params[] = (int) $departemen_id;
}

if ($q !== '') {
    $sql .= ' AND e.nama LIKE ?';
    $params[] = "%$q%";
}

$sql .= ' GROUP BY e.id, e.nik, e.nama, d.nama_departemen ORDER BY e.nama';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rekap = $stmt->fetchAll();

// Total hari kalender dalam periode yang dipilih (termasuk Minggu &
// tanggal merah, karena sebagian karyawan tetap masuk di hari tersebut).
// "Tidak Hadir" dihitung dari selisih total hari periode dengan jumlah
// hari yang punya catatan absensi apa pun (hadir/telat/lembur).
$totalHariPeriode = (int) date('t', strtotime($awal));

foreach ($rekap as &$r) {
    $r['jumlah_alpha'] = max(0, $totalHariPeriode - (int) $r['hari_tercatat']);
}
unset($r);

/**
 * Format total jam lembur untuk tampilan: 12,5 / 8 / 0
 */
function format_jam_lembur($jam): string
{
    $teks = number_format((float) $jam, 2, ',', '.');
    $teks = rtrim(rtrim($teks, '0'), ',');

    return $teks === '' ? '0' : $teks;
}

// ============================================================
// EXPORT XLSX: Sheet 1 = Rekap, Sheet 2 = Detail per karyawan
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    require_once __DIR__ . '/vendor/autoload.php';

    // --- Query detail harian (filter sama dengan rekap) ---
    $sqlDetail = "SELECT e.nik, e.nama, d.nama_departemen, a.tanggal, a.status,
                         a.jam_masuk_aktual AS jam_masuk, a.jam_pulang_aktual AS jam_keluar,
                         a.menit_telat, a.jam_lembur, a.catatan
                  FROM attendance_daily a
                  JOIN employees e ON e.id = a.employee_id
                  LEFT JOIN departments d ON d.id = e.departemen_id
                  WHERE e.status_aktif = 1 AND a.tanggal BETWEEN ? AND ?";
    $paramsDetail = [$awal, $akhir];

    if ($departemen_id !== '') {
        $sqlDetail .= ' AND e.departemen_id = ?';
        $paramsDetail[] = (int) $departemen_id;
    }
    if ($q !== '') {
        $sqlDetail .= ' AND e.nama LIKE ?';
        $paramsDetail[] = "%$q%";
    }
    $sqlDetail .= ' ORDER BY e.nama, a.tanggal';

    $stmtD = $pdo->prepare($sqlDetail);
    $stmtD->execute($paramsDetail);
    $detail = $stmtD->fetchAll();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $periode = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);

    // --- Sheet 1: Rekap ---
    $s1 = $spreadsheet->getActiveSheet();
    $s1->setTitle('laporan-absensi-' . $periode);
    $s1->fromArray(['ID', 'Nama', 'Departemen', 'Hadir', 'Telat', 'Tidak Hadir', 'Lembur (jam)'], null, 'A1');
    $row = 2;
    foreach ($rekap as $r) {
        // ID dipaksa jadi teks supaya tidak berubah format (mis. JI26030059 / angka panjang)
        $s1->setCellValueExplicit("A$row", (string) $r['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $s1->setCellValue("B$row", $r['nama']);
        $s1->setCellValue("C$row", $r['nama_departemen'] ?? '-');
        $s1->setCellValue("D$row", (int) $r['jumlah_hadir']);
        $s1->setCellValue("E$row", (int) $r['jumlah_telat']);
        $s1->setCellValue("F$row", (int) $r['jumlah_alpha']);
        $s1->setCellValue("G$row", round((float) $r['total_jam_lembur'], 2));
        $row++;
    }

    // --- Sheet 2: Detail per karyawan ---
    $s2 = $spreadsheet->createSheet();
    $s2->setTitle('Detail Karyawan');
    $s2->fromArray(['ID', 'Nama', 'Departemen', 'Tanggal', 'Status', 'Jam Masuk', 'Jam Keluar', 'Telat (menit)', 'Lembur (jam)', 'Catatan'], null, 'A1');
    $row = 2;
    foreach ($detail as $d) {
        $s2->setCellValueExplicit("A$row", (string) $d['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $s2->setCellValue("B$row", $d['nama']);
        $s2->setCellValue("C$row", $d['nama_departemen'] ?? '-');
        $s2->setCellValue("D$row", $d['tanggal']);
        $s2->setCellValue("E$row", $d['status']);
        $s2->setCellValue("F$row", $d['jam_masuk'] ?? '-');
        $s2->setCellValue("G$row", $d['jam_keluar'] ?? '-');
        $s2->setCellValue("H$row", $d['menit_telat'] ?? 0);
        $s2->setCellValue("I$row", $d['jam_lembur'] ?? 0);
        $s2->setCellValue("J$row", $d['catatan'] ?? '');
        $row++;
    }

    // --- Styling header + freeze + lebar kolom otomatis ---
    foreach ([$s1, $s2] as $s) {
        $lastCol = $s->getHighestColumn();
        $s->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $s->freezePane('A2');
        foreach (range('A', $lastCol) as $col) {
            $s->getColumnDimension($col)->setAutoSize(true);
        }
    }
    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="laporan-absensi-' . $periode . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

$departemen_list = $pdo->query('SELECT * FROM departments ORDER BY nama_departemen')->fetchAll();
$qs = http_build_query(['bulan' => $bulan, 'tahun' => $tahun, 'departemen_id' => $departemen_id, 'q' => $q]);

// Untuk link ke Log Absensi dengan periode & departemen yang sama
$qs_absensi = http_build_query([
    'dari' => $awal,
    'sampai' => $akhir,
    'departemen_id' => $departemen_id,
]);

$page_title = 'Laporan';
$nav_active = 'laporan';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Laporan Absensi</h1>
    <p class="page__description">Rekap kehadiran bulanan per karyawan, untuk keperluan payroll dan evaluasi.</p>
  </div>
  <div class="page__action" style="display:flex; gap:8px; flex-wrap:wrap;">
    <a href="absensi.php?<?= e($qs_absensi) ?>" class="button button--ghost button--neutral">
      <?= ic('solar:checklist-minimalistic-linear') ?> Lihat Log Absensi
    </a>
    <a href="?<?= e($qs) ?>&export=xlsx" class="button button--neutral">
      <?= ic('solar:download-linear') ?> Export Excel
    </a>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <div class="card">
      <div class="card__body">
        <form method="get" class="grid grid-cols-12 gap-4 items-end">
          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Cari Nama</label>
              <input type="search" name="q" class="input" placeholder="Nama karyawan…" value="<?= e($q) ?>">
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Bulan</label>
              <select name="bulan" class="select">
                <?php for ($b = 1; $b <= 12; $b++): ?>
                  <option value="<?= $b ?>" <?= $b === $bulan ? 'selected' : '' ?>><?= nama_bulan($b) ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Tahun</label>
              <input type="number" name="tahun" class="input" value="<?= $tahun ?>" min="<?= $tahun_min ?>" max="<?= $tahun_max ?>">
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Departemen</label>
              <select name="departemen_id" class="select">
                <option value="">Semua</option>
                <?php foreach ($departemen_list as $d): ?>
                  <option value="<?= (int) $d['id'] ?>" <?= (string) $departemen_id === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['nama_departemen']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-span-12 lg:col-span-3">
            <button type="submit" class="button button--neutral button--block"><?= ic('solar:tuning-2-linear') ?> Tampilkan</button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <section class="page__section">
    <div class="card">
      <div class="card__header">
        <span class="card__title">Rekap <?= e(nama_bulan($bulan)) ?> <?= $tahun ?></span>
      </div>
      <?php if (empty($rekap)): ?>
        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:chart-2-bold-duotone') ?></div>
          <h3 class="empty-state__title">Tidak ada data</h3>
          <p class="empty-state__text">Belum ada karyawan aktif atau data absensi pada periode ini.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table--hover table--align-middle">
            <thead class="table__head--alt">
              <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Departemen</th>
                <th class="text-end">Hadir</th>
                <th class="text-end">Telat</th>
                <th class="text-end">Tidak Hadir</th>
                <th class="text-end">Lembur (jam)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rekap as $r): ?>
                <tr>
                  <td><?= e($r['nik']) ?></td>
                  <td class="font-medium"><?= e($r['nama']) ?></td>
                  <td><?= e($r['nama_departemen'] ?? '-') ?></td>
                  <td class="text-end"><?= (int) $r['jumlah_hadir'] ?></td>
                  <td class="text-end"><?= (int) $r['jumlah_telat'] ?></td>
                  <td class="text-end"><?= (int) $r['jumlah_alpha'] ?></td>
                  <td class="text-end"><?= e(format_jam_lembur($r['total_jam_lembur'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>