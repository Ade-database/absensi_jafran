<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$hari_ini = date('Y-m-d');

$total_aktif = (int) $pdo->query('SELECT COUNT(*) FROM employees WHERE status_aktif = 1')->fetchColumn();

$stmt = $pdo->prepare('SELECT status, COUNT(*) AS jumlah FROM attendance_daily WHERE tanggal = ? GROUP BY status');
$stmt->execute([$hari_ini]);
$rekap = ['hadir' => 0, 'telat' => 0, 'alpha' => 0, 'lembur' => 0, 'izin' => 0];
foreach ($stmt->fetchAll() as $row) {
    $rekap[$row['status']] = (int) $row['jumlah'];
}
$sudah_absen = $rekap['hadir'] + $rekap['telat'] + $rekap['lembur'];
$belum_absen = max(0, $total_aktif - $sudah_absen);

$stmt = $pdo->prepare(
    'SELECT e.nama, e.nik, d.nama_departemen, a.jam_masuk_aktual, a.jam_pulang_aktual, a.status
     FROM attendance_daily a
     JOIN employees e ON e.id = a.employee_id
     LEFT JOIN departments d ON d.id = e.departemen_id
     WHERE a.tanggal = ?
     ORDER BY a.jam_masuk_aktual DESC
     LIMIT 15'
);
$stmt->execute([$hari_ini]);
$aktivitas = $stmt->fetchAll();

$page_title = 'Dashboard';
$nav_active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Dashboard</h1>
    <p class="page__description"><?= e(tgl_indo($hari_ini)) ?></p>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

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
              <div class="stat__label text-eyebrow">Hadir Hari Ini</div>
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
              <div class="stat__label text-eyebrow">Telat Hari Ini</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 sm:col-span-6 lg:col-span-3">
        <div class="card card--stat">
          <div class="card__body">
            <span class="icon-box icon-box--danger icon-box--lg"><?= ic('solar:close-circle-bold') ?></span>
            <div class="stat">
              <div class="stat__value"><?= (int) $belum_absen ?></div>
              <div class="stat__label text-eyebrow">Belum Absen</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="page__section">
    <div class="card">
      <div class="card__header">
        <span class="card__title">Aktivitas Absensi Hari Ini</span>
        <div class="card__action">
          <a href="absensi.php" class="button button--sm button--ghost button--primary">Lihat semua</a>
        </div>
      </div>
      <?php if (empty($aktivitas)): ?>
        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:checklist-minimalistic-bold-duotone') ?></div>
          <h3 class="empty-state__title">Belum ada absensi hari ini</h3>
          <p class="empty-state__text">Data akan muncul otomatis setelah karyawan absen di mesin, atau bisa ditambahkan manual lewat menu Log Absensi.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table--hover table--align-middle">
            <thead class="table__head--alt">
              <tr>
                <th>Karyawan</th>
                <th>Departemen</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aktivitas as $a): ?>
                <tr>
                  <td>
                    <div class="font-medium"><?= e($a['nama']) ?></div>
                    <div class="text-xs text-muted-foreground"><?= e($a['nik']) ?></div>
                  </td>
                  <td><?= e($a['nama_departemen'] ?? '-') ?></td>
                  <td><?= jam($a['jam_masuk_aktual']) ?></td>
                  <td><?= jam($a['jam_pulang_aktual']) ?></td>
                  <td><?= badge_status($a['status']) ?></td>
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
