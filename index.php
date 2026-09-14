<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$bulan_dipilih = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('n');
$tahun_dipilih = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if ($bulan_dipilih < 1 || $bulan_dipilih > 12) {
    $bulan_dipilih = (int) date('n');
}

$awal_bulan = sprintf('%04d-%02d-01', $tahun_dipilih, $bulan_dipilih);
$akhir_bulan = date('Y-m-t', strtotime($awal_bulan));

$total_aktif = (int) $pdo->query('SELECT COUNT(*) FROM employees WHERE status_aktif = 1')->fetchColumn();

$stmt = $pdo->prepare('SELECT status, COUNT(*) AS jumlah FROM attendance_daily WHERE tanggal BETWEEN ? AND ? GROUP BY status');
$stmt->execute([$awal_bulan, $akhir_bulan]);
$rekap = ['hadir' => 0, 'telat' => 0, 'alpha' => 0, 'lembur' => 0, 'izin' => 0];
foreach ($stmt->fetchAll() as $row) {
    $rekap[$row['status']] = (int) $row['jumlah'];
}
$tidak_hadir = $rekap['alpha'];

// Data harian untuk grafik tren absensi selama bulan yang dipilih
$stmt = $pdo->prepare(
    'SELECT tanggal, status, COUNT(*) AS jumlah
     FROM attendance_daily
     WHERE tanggal BETWEEN ? AND ?
     GROUP BY tanggal, status
     ORDER BY tanggal'
);
$stmt->execute([$awal_bulan, $akhir_bulan]);
$data_harian = $stmt->fetchAll();

$jumlah_hari_bulan = (int) date('t', strtotime($awal_bulan));

$grafik_label = [];
$grafik_hadir = [];
$grafik_telat = [];
$grafik_alpha = [];

for ($d = 1; $d <= $jumlah_hari_bulan; $d++) {
    $grafik_label[$d] = (string) $d;
    $grafik_hadir[$d] = 0;
    $grafik_telat[$d] = 0;
    $grafik_alpha[$d] = 0;
}

foreach ($data_harian as $row) {
    $hari = (int) date('j', strtotime($row['tanggal']));
    if (!isset($grafik_hadir[$hari])) {
        continue;
    }
    if ($row['status'] === 'hadir') {
        $grafik_hadir[$hari] = (int) $row['jumlah'];
    } elseif ($row['status'] === 'telat') {
        $grafik_telat[$hari] = (int) $row['jumlah'];
    } elseif ($row['status'] === 'alpha') {
        $grafik_alpha[$hari] = (int) $row['jumlah'];
    }
}

$ada_data_grafik = array_sum($grafik_hadir) + array_sum($grafik_telat) + array_sum($grafik_alpha) > 0;

$tahun_awal_data = (int) $pdo->query('SELECT YEAR(MIN(tanggal)) FROM attendance_daily')->fetchColumn();
if (!$tahun_awal_data) {
    $tahun_awal_data = (int) date('Y');
}
$tahun_sekarang = (int) date('Y');

$page_title = 'Dashboard';
$nav_active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Dashboard</h1>
    <p class="page__description"><?= e($nama_bulan[$bulan_dipilih] . ' ' . $tahun_dipilih) ?></p>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <form method="get" class="grid grid-cols-12 gap-4 items-end">
      <div class="col-span-6 sm:col-span-4 lg:col-span-3">
        <div class="field">
          <label class="field__label" for="bulan">Bulan</label>
          <select name="bulan" id="bulan" class="select" onchange="this.form.submit()">
            <?php foreach ($nama_bulan as $angka => $nama): ?>
              <option value="<?= $angka ?>" <?= $angka === $bulan_dipilih ? 'selected' : '' ?>><?= e($nama) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-span-6 sm:col-span-4 lg:col-span-3">
        <div class="field">
          <label class="field__label" for="tahun">Tahun</label>
          <select name="tahun" id="tahun" class="select" onchange="this.form.submit()">
            <?php for ($t = $tahun_sekarang; $t >= $tahun_awal_data; $t--): ?>
              <option value="<?= $t ?>" <?= $t === $tahun_dipilih ? 'selected' : '' ?>><?= $t ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <noscript>
        <div class="col-span-12 sm:col-span-4 lg:col-span-3">
          <button type="submit" class="button button--sm button--primary">Terapkan</button>
        </div>
      </noscript>
    </form>
  </section>

  <section class="page__section">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12 sm:col-span-6 lg:col-span-3">
        <div class="card card--stat">
          <div class="card__body">
            <span class="icon-box icon-box--primary icon-box--lg"><?= ic('solar:users-group-rounded-bold-duotone') ?></span>
            <div class="stat">
              <div class="stat__value"><?= (int) $total_aktif ?></div>
              <div class="stat__label text-eyebrow">Karyawan Aktif</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 sm:col-span-6 lg:col-span-3">
        <div class="card card--stat">
          <div class="card__body">
            <span class="icon-box icon-box--success icon-box--lg"><?= ic('solar:check-circle-bold') ?></span>
            <div class="stat">
              <div class="stat__value"><?= (int) $rekap['hadir'] ?></div>
              <div class="stat__label text-eyebrow">Hadir Bulan Ini</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 sm:col-span-6 lg:col-span-3">
        <div class="card card--stat">
          <div class="card__body">
            <span class="icon-box icon-box--warning icon-box--lg"><?= ic('solar:danger-triangle-bold-duotone') ?></span>
            <div class="stat">
              <div class="stat__value"><?= (int) $rekap['telat'] ?></div>
              <div class="stat__label text-eyebrow">Telat Bulan Ini</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 sm:col-span-6 lg:col-span-3">
        <div class="card card--stat">
          <div class="card__body">
            <span class="icon-box icon-box--danger icon-box--lg"><?= ic('solar:close-circle-bold') ?></span>
            <div class="stat">
              <div class="stat__value"><?= (int) $tidak_hadir ?></div>
              <div class="stat__label text-eyebrow">Tidak Hadir Bulan Ini</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="page__section">
    <div class="card">
      <div class="card__header">
        <span class="card__title">Grafik Absensi <?= e($nama_bulan[$bulan_dipilih] . ' ' . $tahun_dipilih) ?></span>
        <div class="card__action">
          <a href="absensi.php" class="button button--sm button--ghost button--primary">Lihat semua</a>
        </div>
      </div>

      <?php if (!$ada_data_grafik): ?>
        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:checklist-minimalistic-bold-duotone') ?></div>
          <h3 class="empty-state__title">Belum ada data absensi bulan ini</h3>
          <p class="empty-state__text">Data akan muncul otomatis setelah karyawan absen di mesin, atau bisa ditambahkan manual lewat menu Log Absensi.</p>
        </div>
      <?php else: ?>
        <div class="card__body" style="padding: 16px 20px;">
          <div style="position: relative; height: 340px;">
            <canvas id="grafikAbsensiBulanan"></canvas>
          </div>
        </div>

        <script src="assets/vendor/chartjs/chart.umd.js"></script>
        <script>
          (function () {
            const ctx = document.getElementById('grafikAbsensiBulanan');
            if (!ctx) return;

            new Chart(ctx, {
              type: 'bar',
              data: {
                labels: <?= json_encode(array_values($grafik_label)) ?>,
                datasets: [
                  {
                    label: 'Hadir',
                    data: <?= json_encode(array_values($grafik_hadir)) ?>,
                    backgroundColor: '#16a34a',
                    borderRadius: 3,
                  },
                  {
                    label: 'Telat',
                    data: <?= json_encode(array_values($grafik_telat)) ?>,
                    backgroundColor: '#d97706',
                    borderRadius: 3,
                  },
                  {
                    label: 'Tidak Hadir',
                    data: <?= json_encode(array_values($grafik_alpha)) ?>,
                    backgroundColor: '#dc2626',
                    borderRadius: 3,
                  },
                ],
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                  legend: { position: 'bottom' },
                  tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                  x: {
                    title: { display: true, text: 'Tanggal' },
                    stacked: false,
                  },
                  y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    title: { display: true, text: 'Jumlah Karyawan' },
                  },
                },
              },
            });
          })();
        </script>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>