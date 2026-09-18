<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';

wajib_login();

/*
|--------------------------------------------------------------------------
| PROSES POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {

        $employee_id = (int) ($_POST['employee_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? '';
        $jam_masuk = !empty($_POST['jam_masuk_aktual'])
            ? $_POST['jam_masuk_aktual']
            : null;
        $jam_pulang = !empty($_POST['jam_pulang_aktual'])
            ? $_POST['jam_pulang_aktual']
            : null;

        $catatan = trim($_POST['catatan'] ?? '');

        if (!$employee_id || $tanggal === '') {

            flash('error', 'Karyawan dan tanggal wajib diisi.');

        } else {

            $emp = $pdo->prepare(
                'SELECT
                    e.*,
                    s.jam_masuk AS shift_masuk,
                    s.toleransi_menit,
                    s.is_lembur
                 FROM employees e
                 LEFT JOIN shifts s ON s.id = e.shift_id
                 WHERE e.id = ?'
            );

            $emp->execute([$employee_id]);

            $emp = $emp->fetch();

            if (!$emp) {

                flash('error', 'Karyawan tidak ditemukan.');

            } else {

                if ($emp['shift_masuk']) {

                    [$status, $menit_telat] = hitung_status(
                        $jam_masuk,
                        $emp['shift_masuk'],
                        (int) $emp['toleransi_menit']
                    );

                    if ($status === 'hadir' && !empty($emp['is_lembur'])) {
                        $status = 'lembur';
                    }

                } else {

                    $status = $jam_masuk ? 'hadir' : 'alpha';
                    $menit_telat = 0;
                }

                try {

                    $pdo->prepare(
                        "INSERT INTO attendance_daily
                        (
                            employee_id,
                            tanggal,
                            jam_masuk_aktual,
                            jam_pulang_aktual,
                            status,
                            menit_telat,
                            catatan,
                            perlu_tinjau,
                            sumber
                        )
                        VALUES (?,?,?,?,?,?,?,0,'manual')

                        ON DUPLICATE KEY UPDATE

                            jam_masuk_aktual = VALUES(jam_masuk_aktual),
                            jam_pulang_aktual = VALUES(jam_pulang_aktual),
                            status = VALUES(status),
                            menit_telat = VALUES(menit_telat),
                            catatan = VALUES(catatan),
                            perlu_tinjau = 0,
                            sumber = 'manual'"
                    )->execute([
                        $employee_id,
                        $tanggal,
                        $jam_masuk,
                        $jam_pulang,
                        $status,
                        $menit_telat,
                        $catatan
                    ]);

                    flash('success', 'Absensi berhasil disimpan.');

                } catch (PDOException $e) {

                    $cek = $pdo->prepare(
                        'SELECT id
                         FROM attendance_daily
                         WHERE employee_id = ?
                         AND tanggal = ?'
                    );

                    $cek->execute([$employee_id, $tanggal]);

                    $ada = $cek->fetch();

                    if ($ada) {

                        $pdo->prepare(
                            "UPDATE attendance_daily
                             SET
                                jam_masuk_aktual = ?,
                                jam_pulang_aktual = ?,
                                status = ?,
                                menit_telat = ?,
                                catatan = ?,
                                perlu_tinjau = 0,
                                sumber = 'manual'
                             WHERE id = ?"
                        )->execute([
                            $jam_masuk,
                            $jam_pulang,
                            $status,
                            $menit_telat,
                            $catatan,
                            $ada['id']
                        ]);

                    } else {

                        $pdo->prepare(
                            "INSERT INTO attendance_daily
                            (
                                employee_id,
                                tanggal,
                                jam_masuk_aktual,
                                jam_pulang_aktual,
                                status,
                                menit_telat,
                                catatan,
                                perlu_tinjau,
                                sumber
                            )
                            VALUES (?,?,?,?,?,?,?,0,'manual')"
                        )->execute([
                            $employee_id,
                            $tanggal,
                            $jam_masuk,
                            $jam_pulang,
                            $status,
                            $menit_telat,
                            $catatan
                        ]);
                    }

                    flash('success', 'Absensi berhasil disimpan.');
                }
            }
        }
    }

    elseif ($aksi === 'hapus') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $pdo->prepare('DELETE FROM attendance_daily WHERE id = ?');
            $stmt->execute([$id]);

            flash('success', 'Data absensi berhasil dihapus.');

        } else {

            flash('error', 'ID data absensi tidak valid.');
        }
    }

    elseif ($aksi === 'hapus_batch') {

        $ids = $_POST['ids'] ?? [];

        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_map('intval', $ids);

        $ids = array_values(
            array_filter($ids, function ($id) {
                return $id > 0;
            })
        );

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {

            flash('error', 'Tidak ada data yang dipilih.');

        } else {

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $pdo->prepare(
                "DELETE FROM attendance_daily WHERE id IN ($placeholders)"
            );

            $stmt->execute($ids);

            $jumlah = $stmt->rowCount();

            flash('success', $jumlah . ' data absensi berhasil dihapus.');
        }
    }

    elseif ($aksi === 'hapus_semua') {

        $stmt = $pdo->prepare('DELETE FROM attendance_daily');
        $stmt->execute();

        $jumlah = $stmt->rowCount();

        flash('success', $jumlah . ' data absensi berhasil dihapus.');
    }

    redirect(
        'absensi.php' .
        (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : '')
    );
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$tanggal_dari = $_GET['dari'] ?? date('Y-m-01');
$tanggal_sampai = $_GET['sampai'] ?? date('Y-m-d');
$departemen_id = $_GET['departemen_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$hanya_tinjau = !empty($_GET['hanya_tinjau']);

if ($departemen_id !== '' && !ctype_digit((string) $departemen_id)) {
    $departemen_id = '';
}

$status_valid = ['hadir', 'telat', 'alpha', 'lembur'];
if ($status_filter !== '' && !in_array($status_filter, $status_valid, true)) {
    $status_filter = '';
}

// Bagian WHERE dipakai bersama oleh query hitung total (untuk pagination)
// dan query pengambilan data, supaya jumlah halaman selalu sinkron dengan
// filter yang sedang aktif.
$where = ' WHERE a.tanggal BETWEEN ? AND ? ';
$params = [$tanggal_dari, $tanggal_sampai];

if ($departemen_id !== '') {
    $where .= ' AND e.departemen_id = ? ';
    $params[] = (int) $departemen_id;
}

if ($status_filter !== '') {
    $where .= ' AND a.status = ? ';
    $params[] = $status_filter;
}

if ($hanya_tinjau) {
    $where .= ' AND a.perlu_tinjau = 1 ';
}

$from_join = '
    FROM attendance_daily a
    JOIN employees e ON e.id = a.employee_id
    LEFT JOIN departments d ON d.id = e.departemen_id
';

// Hitung total data yang cocok filter, untuk keperluan pagination
$stmt_total = $pdo->prepare('SELECT COUNT(*) ' . $from_join . $where);
$stmt_total->execute($params);
$total_data = (int) $stmt_total->fetchColumn();

$per_halaman = 25;
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));

$halaman = (int) ($_GET['halaman'] ?? 1);
if ($halaman < 1) {
    $halaman = 1;
}
if ($halaman > $total_halaman) {
    $halaman = $total_halaman;
}

$offset = ($halaman - 1) * $per_halaman;

$sql = '
    SELECT
        a.*,
        e.nama,
        e.nik,
        d.nama_departemen
    ' . $from_join . $where . '
    ORDER BY a.tanggal DESC, e.nama
    LIMIT ' . (int) $per_halaman . ' OFFSET ' . (int) $offset . '
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$karyawan_list = $pdo->query(
    'SELECT id, nik, nama FROM employees WHERE status_aktif = 1 ORDER BY nama'
)->fetchAll();

$departemen_list = $pdo->query(
    'SELECT * FROM departments ORDER BY nama_departemen'
)->fetchAll();

// $qs: dipakai untuk redirect_qs setelah tambah/hapus data, ikut membawa
// halaman aktif supaya user tidak lompat balik ke halaman 1.
$qs = http_build_query([
    'dari' => $tanggal_dari,
    'sampai' => $tanggal_sampai,
    'departemen_id' => $departemen_id,
    'status' => $status_filter,
    'hanya_tinjau' => $hanya_tinjau ? 1 : '',
    'halaman' => $halaman,
]);

// $qs_filter_saja: query string filter TANPA parameter halaman, dipakai
// untuk membangun link navigasi antar halaman (tinggal tambah &halaman=N).
$qs_filter_saja = http_build_query([
    'dari' => $tanggal_dari,
    'sampai' => $tanggal_sampai,
    'departemen_id' => $departemen_id,
    'status' => $status_filter,
    'hanya_tinjau' => $hanya_tinjau ? 1 : '',
]);

// Untuk link ke Laporan dengan periode & departemen yang sama.
// Bulan/tahun diambil dari tanggal "Dari" yang sedang aktif difilter.
$bulan_untuk_laporan = (int) date('n', strtotime($tanggal_dari));
$tahun_untuk_laporan = (int) date('Y', strtotime($tanggal_dari));
$qs_laporan = http_build_query([
    'bulan' => $bulan_untuk_laporan,
    'tahun' => $tahun_untuk_laporan,
    'departemen_id' => $departemen_id,
]);

$page_title = 'Log Absensi';
$nav_active = 'absensi';

include __DIR__ . '/includes/header.php';

?>

<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Log Absensi</h1>
    <p class="page__description">Riwayat kehadiran karyawan. Data terisi otomatis dari mesin setelah integrasi aktif; sementara bisa ditambah manual.</p>
  </div>
  <div class="page__action" style="display:flex; gap:8px; flex-wrap:wrap;">
    <a href="laporan.php?<?= e($qs_laporan) ?>" class="button button--ghost button--neutral">
      <?= ic('solar:chart-2-linear') ?> Lihat Laporan
    </a>
    <button type="button" class="button button--primary" data-stisla-dialog-trigger="tambahAbsensi">
      <?= ic('solar:add-circle-linear') ?> Tambah Manual
    </button>
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
              <label class="field__label">Dari Tanggal</label>
              <input type="date" name="dari" class="input" value="<?= e($tanggal_dari) ?>">
            </div>
          </div>

          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Sampai Tanggal</label>
              <input type="date" name="sampai" class="input" value="<?= e($tanggal_sampai) ?>">
            </div>
          </div>

          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Departemen</label>
              <select name="departemen_id" class="select">
                <option value="">Semua</option>
                <?php foreach ($departemen_list as $d): ?>
                  <option value="<?= (int) $d['id'] ?>" <?= (string) $departemen_id === (string) $d['id'] ? 'selected' : '' ?>>
                    <?= e($d['nama_departemen']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="field">
              <label class="field__label">Status</label>
              <select name="status" class="select">
                <option value="">Semua</option>
                <?php foreach (
                    [
                        'hadir' => 'Hadir',
                        'telat' => 'Telat',
                        'alpha' => 'Tidak Hadir',
                        'lembur' => 'Lembur'
                    ] as $val => $label
                ): ?>
                  <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>>
                    <?= $label ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-span-12">
            <label class="field__item mb-3">
              <input type="checkbox" class="checkbox" name="hanya_tinjau" value="1" <?= $hanya_tinjau ? 'checked' : '' ?>>
              <span class="field__label">Tampilkan yang perlu ditinjau saja</span>
            </label>
            <br>
            <button type="submit" class="button button--neutral">
              <?= ic('solar:tuning-2-linear') ?> Terapkan Filter
            </button>
          </div>

        </form>
      </div>
    </div>
  </section>

  <section class="page__section">
    <div class="card">

      <?php if (empty($logs)): ?>

        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:checklist-minimalistic-bold-duotone') ?></div>
          <h3 class="empty-state__title">Tidak ada data pada rentang ini</h3>
          <p class="empty-state__text">Coba ubah filter tanggal, atau tambahkan data absensi manual.</p>
        </div>

      <?php else: ?>

        <div class="card__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <span class="card__title">Data Absensi</span>
            <div class="text-xs text-muted-foreground" style="margin-top:4px;">
              Menampilkan <?= count($logs) ?> dari <?= $total_data ?> data
              (halaman <?= $halaman ?> dari <?= $total_halaman ?>)
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <button type="submit" form="formHapusBatch" class="button button--sm button--ghost button--danger" id="btnHapusDipilih" disabled onclick="return konfirmasiHapusDipilih();">
              <?= ic('solar:trash-bin-minimalistic-linear') ?> Hapus yang Dipilih
              <span id="jumlahDipilih">(0)</span>
            </button>

            <form method="post" style="display:inline;" onsubmit="return konfirmasiHapusSemua();">
              <input type="hidden" name="aksi" value="hapus_semua">
              <input type="hidden" name="redirect_qs" value="<?= e($qs) ?>">
              <button type="submit" class="button button--sm button--danger">
                <?= ic('solar:trash-bin-trash-linear') ?> Hapus Semua
              </button>
            </form>
          </div>
        </div>

        <form method="post" id="formHapusBatch">
          <input type="hidden" name="aksi" value="hapus_batch">
          <input type="hidden" name="redirect_qs" value="<?= e($qs) ?>">

          <div class="table-responsive">
            <table class="table table--hover table--align-middle">
              <thead class="table__head--alt">
                <tr>
                  <th style="width:50px; text-align:center;">
                    <input type="checkbox" class="checkbox" id="checkSemua" title="Pilih semua">
                  </th>
                  <th>Tanggal</th>
                  <th>Karyawan</th>
                  <th>Departemen</th>
                  <th>Jam Masuk</th>
                  <th>Jam Pulang</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td style="text-align:center;">
                      <input type="checkbox" class="checkbox checkbox-data" name="ids[]" value="<?= (int) $l['id'] ?>">
                    </td>
                    <td><?= e(tgl_indo($l['tanggal'], false)) ?></td>
                    <td>
                      <div class="font-medium"><?= e($l['nama']) ?></div>
                      <div class="text-xs text-muted-foreground"><?= e($l['nik']) ?></div>
                    </td>
                    <td><?= e($l['nama_departemen'] ?? '-') ?></td>
                    <td><?= jam($l['jam_masuk_aktual']) ?></td>
                    <td><?= jam($l['jam_pulang_aktual']) ?></td>
                    <td>
                      <?= badge_status($l['status']) ?>
                      <?php if ($l['status'] === 'telat'): ?>
                        <span class="text-xs text-muted-foreground">(<?= (int) $l['menit_telat'] ?> menit)</span>
                      <?php endif; ?>
                      <?php if (!empty($l['perlu_tinjau'])): ?>
                        <span class="badge badge--soft badge--warning">Perlu Ditinjau</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <form method="post" style="display:inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data absensi ini?');">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <input type="hidden" name="redirect_qs" value="<?= e($qs) ?>">
                        <button type="submit" class="button button--sm button--ghost button--danger" title="Hapus data">
                          <?= ic('solar:trash-bin-minimalistic-linear') ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </form>

        <?php if ($total_halaman > 1): ?>
          <div class="card__footer" style="display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; padding:16px;">

            <a href="?<?= e($qs_filter_saja) ?>&halaman=<?= max(1, $halaman - 1) ?>"
               class="button button--sm button--ghost button--neutral"
               <?= $halaman <= 1 ? 'aria-disabled="true" tabindex="-1" style="pointer-events:none; opacity:.5;"' : '' ?>>
              <?= ic('solar:alt-arrow-left-linear') ?> Sebelumnya
            </a>

            <?php
              // Tampilkan maksimal 5 nomor halaman di sekitar halaman aktif,
              // supaya navigasi tidak kepanjangan kalau total halaman banyak.
              $mulai = max(1, $halaman - 2);
              $selesai = min($total_halaman, $mulai + 4);
              $mulai = max(1, $selesai - 4);
            ?>

            <?php if ($mulai > 1): ?>
              <a href="?<?= e($qs_filter_saja) ?>&halaman=1" class="button button--sm button--ghost button--neutral">1</a>
              <?php if ($mulai > 2): ?><span class="text-muted-foreground">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $mulai; $p <= $selesai; $p++): ?>
              <a href="?<?= e($qs_filter_saja) ?>&halaman=<?= $p ?>"
                 class="button button--sm <?= $p === $halaman ? 'button--primary' : 'button--ghost button--neutral' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>

            <?php if ($selesai < $total_halaman): ?>
              <?php if ($selesai < $total_halaman - 1): ?><span class="text-muted-foreground">…</span><?php endif; ?>
              <a href="?<?= e($qs_filter_saja) ?>&halaman=<?= $total_halaman ?>" class="button button--sm button--ghost button--neutral"><?= $total_halaman ?></a>
            <?php endif; ?>

            <a href="?<?= e($qs_filter_saja) ?>&halaman=<?= min($total_halaman, $halaman + 1) ?>"
               class="button button--sm button--ghost button--neutral"
               <?= $halaman >= $total_halaman ? 'aria-disabled="true" tabindex="-1" style="pointer-events:none; opacity:.5;"' : '' ?>>
              Berikutnya <?= ic('solar:alt-arrow-right-linear') ?>
            </a>

          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </section>

</div>

<div class="dialog" id="tambahAbsensi" data-stisla-dialog data-state="closed" role="dialog" aria-modal="true" tabindex="-1">
  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
  <div class="dialog__panel">
    <div class="dialog__content">
      <form method="post">
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="redirect_qs" value="<?= e($qs) ?>">

        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup">
          <?= ic('solar:close-circle-linear') ?>
        </button>

        <div class="dialog__header">
          <h3 class="dialog__title">Tambah Absensi Manual</h3>
        </div>

        <div class="dialog__body flex flex-col gap-4">

          <div class="field">
            <label class="field__label">Karyawan</label>
            <select name="employee_id" class="select" required>
              <option value="">— Pilih Karyawan —</option>
              <?php foreach ($karyawan_list as $k): ?>
                <option value="<?= (int) $k['id'] ?>">
                  <?= e($k['nama']) ?> (<?= e($k['nik']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label class="field__label">Tanggal</label>
            <input type="date" name="tanggal" class="input" value="<?= e(date('Y-m-d')) ?>" required>
          </div>

          <div class="grid grid-cols-12 gap-4">
            <div class="col-span-6">
              <div class="field">
                <label class="field__label">Jam Masuk</label>
                <input type="time" name="jam_masuk_aktual" class="input">
              </div>
            </div>
            <div class="col-span-6">
              <div class="field">
                <label class="field__label">Jam Pulang</label>
                <input type="time" name="jam_pulang_aktual" class="input">
              </div>
            </div>
          </div>

          <div class="field">
            <label class="field__label">Catatan (opsional)</label>
            <input type="text" name="catatan" class="input" placeholder="mis: lupa absen, dicatat manual oleh admin">
          </div>

          <div class="alert alert--neutral">
            <?= ic('solar:danger-triangle-bold-duotone') ?>
            <span class="text-sm">
              Status (Hadir/Telat/Tidak Hadir) dihitung otomatis berdasarkan shift karyawan yang bersangkutan. Kosongkan jam masuk kalau karyawan tidak hadir.
            </span>
          </div>

        </div>

        <div class="dialog__footer">
          <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
          <button type="submit" class="button button--primary">Simpan</button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const checkSemua = document.getElementById('checkSemua');
    const checkboxData = document.querySelectorAll('.checkbox-data');
    const btnHapusDipilih = document.getElementById('btnHapusDipilih');
    const jumlahDipilih = document.getElementById('jumlahDipilih');

    function updatePilihan() {

        const jumlah = document.querySelectorAll('.checkbox-data:checked').length;

        jumlahDipilih.textContent = '(' + jumlah + ')';

        btnHapusDipilih.disabled = jumlah === 0;

        if (jumlah === 0) {
            checkSemua.checked = false;
            checkSemua.indeterminate = false;
        } else if (jumlah === checkboxData.length) {
            checkSemua.checked = true;
            checkSemua.indeterminate = false;
        } else {
            checkSemua.checked = false;
            checkSemua.indeterminate = true;
        }
    }

    if (checkSemua) {
        checkSemua.addEventListener('change', function () {
            checkboxData.forEach(function (checkbox) {
                checkbox.checked = checkSemua.checked;
            });
            updatePilihan();
        });
    }

    checkboxData.forEach(function (checkbox) {
        checkbox.addEventListener('change', updatePilihan);
    });

    updatePilihan();

});

function konfirmasiHapusDipilih() {

    const jumlah = document.querySelectorAll('.checkbox-data:checked').length;

    if (jumlah === 0) {
        alert('Silakan pilih data absensi terlebih dahulu.');
        return false;
    }

    return confirm(
        'Apakah Anda yakin ingin menghapus ' + jumlah + ' data absensi yang dipilih?\n\n' +
        'Data yang sudah dihapus tidak dapat dikembalikan.'
    );
}

function konfirmasiHapusSemua() {
    return confirm(
        '⚠️ PERINGATAN!\n\n' +
        'Apakah Anda yakin ingin menghapus SEMUA data absensi?\n\n' +
        'Seluruh data pada tabel attendance_daily akan dihapus.\n\n' +
        'Data yang sudah dihapus tidak dapat dikembalikan.'
    );
}

</script>

<?php include __DIR__ . '/includes/footer.php'; ?>