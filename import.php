<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/import_parser.php';

$step = $_GET['step'] ?? $_POST['step'] ?? 'upload';

/**
 * Ambil data staging 1 batch, sudah digabung dengan data karyawan,
 * dan sudah dihitung jam masuk/pulang + shift terdeteksi + status +
 * lembur. Dipakai bareng oleh halaman preview & proses commit supaya
 * hasilnya selalu konsisten.
 *
 * Shift tidak lagi properti tetap karyawan: untuk tiap baris, sistem
 * mencari shift mana (dari shift yang berlaku di hari itu) yang
 * jangkauannya paling cocok dengan jam masuk hasil tebakan.
 */
function ambil_data_preview(PDO $pdo, string $batch_id): array
{
    // Ambil semua shift sekali saja, dipakai untuk deteksi tiap baris.
    $semua_shift = $pdo->query('SELECT * FROM shifts')->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT s.*, e.nama AS nama_karyawan, e.nik, d.nama_departemen
         FROM import_staging s
         JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.departemen_id
         WHERE s.batch_id = ? AND s.diabaikan = 0 AND s.employee_id IS NOT NULL
         ORDER BY s.tanggal, e.nama"
    );
    $stmt->execute([$batch_id]);
    $rows = $stmt->fetchAll();

    $hasil = [];

    foreach ($rows as $r) {

        $jam_list = $r['jam_raw'] !== null && $r['jam_raw'] !== ''
            ? explode(',', $r['jam_raw'])
            : [];

        // Tidak ada shift tetap lagi, jadi tebakan masuk/pulang murni
        // dari urutan scan. 1x scan otomatis dianggap "jam masuk" dan
        // selalu ditandai (lihat tebak_masuk_pulang()).
        [$masuk, $pulang, $ambigu_deteksi] = tebak_masuk_pulang($jam_list, null, null);

        $shift_hari_ini = array_values(array_filter(
            $semua_shift,
            fn($sh) => shift_berlaku_hari($sh['hari_kerja'], $r['tanggal'])
        ));

        $shift_cocok = null;

        if ($masuk && !empty($shift_hari_ini)) {

            [$shift_cocok, $shift_ambigu] = deteksi_shift($masuk, $shift_hari_ini);

            if (!empty($shift_ambigu)) {
                $ambigu_deteksi = true;
            }
        }

        if ($shift_cocok) {

            [$status, $menit_telat] = hitung_status(
                $masuk,
                $shift_cocok['jam_masuk'],
                (int) $shift_cocok['toleransi_menit']
            );

            $jam_lembur = hitung_lembur($pulang, $shift_cocok['jam_masuk'], $shift_cocok['jam_pulang']);

        } else {

            $status = $masuk ? 'hadir' : 'alpha';
            $menit_telat = 0;
            $jam_lembur = 0;

            if ($masuk) {
                // Karyawan check-in tapi tidak ada shift yang cocok
                // sama sekali -> wajib ditinjau manual.
                $ambigu_deteksi = true;
            }
        }

        // $ambigu_deteksi: khusus kasus sistem RAGU (shift ambigu, tidak
        // ada shift cocok, atau cuma 1x scan) -> ini yang dapat catatan
        // "jam ditebak otomatis" karena butuh verifikasi datanya benar.
        //
        // $perlu_tinjau: flag umum untuk admin -> selain kasus di atas,
        // status yang bukan "hadir" (Telat, Alpha) juga otomatis
        // ditandai, supaya admin bisa langsung lihat siapa saja yang
        // perlu ditindaklanjuti tanpa perlu menyaring manual.
        $perlu_tinjau = $ambigu_deteksi || $status !== 'hadir';

        $hasil[] = [
            'employee_id' => (int) $r['employee_id'],
            'nama' => $r['nama_karyawan'],
            'nik' => $r['nik'],
            'departemen' => $r['nama_departemen'],
            'tanggal' => $r['tanggal'],
            'jam_masuk' => $masuk,
            'jam_pulang' => $pulang,
            'shift_terdeteksi' => $shift_cocok['nama_shift'] ?? null,
            'jumlah_scan' => (int) $r['jumlah_scan'],
            'status' => $status,
            'menit_telat' => $menit_telat,
            'jam_lembur' => $jam_lembur,
            'perlu_tinjau' => $perlu_tinjau,
            'ambigu_deteksi' => $ambigu_deteksi,
        ];
    }
    return $hasil;
}

// =====================================================================
// STEP: upload (form) + proses upload (POST)
// =====================================================================
if ($step === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['file_excel']['tmp_name']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Gagal mengunggah file. Coba lagi.');
        redirect('import.php');
    }

    if (!is_dir(__DIR__ . '/storage/tmp')) {
        mkdir(__DIR__ . '/storage/tmp', 0755, true);
    }

    $namaAsli = $_FILES['file_excel']['name'];
    $extension = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

    if (!in_array($extension, ['xls', 'xlsx'], true)) {
        flash('error', 'Format file harus .xls atau .xlsx.');
        redirect('import.php');
    }

    $tujuan = __DIR__ . '/storage/tmp/' . uniqid('upload_', true) . '.' . $extension;
    $ukuran_asli = (int) $_FILES['file_excel']['size'];

    if (!move_uploaded_file($_FILES['file_excel']['tmp_name'], $tujuan)) {
        flash('error', 'Gagal memindahkan file yang diupload. Cek permission folder storage/tmp.');
        redirect('import.php');
    }

    $ukuran_tersimpan = filesize($tujuan);
    if ($ukuran_tersimpan !== $ukuran_asli || $ukuran_tersimpan === 0) {
        @unlink($tujuan);
        flash('error', "File rusak saat diupload (ukuran asli: $ukuran_asli byte, tersimpan: $ukuran_tersimpan byte). Coba upload ulang.");
        redirect('import.php');
    }

    try {
        $hasil = parse_excel_absensi($tujuan);
    } catch (\Throwable $e) {
        @unlink($tujuan);
        flash('error', 'Gagal membaca file: ' . $e->getMessage());
        redirect('import.php');
    }
    @unlink($tujuan);

    if (empty($hasil['employees'])) {
        flash('error', 'Tidak ada data karyawan yang terbaca dari file ini.');
        redirect('import.php');
    }

    // Auto-buat departemen yang belum ada.
    $dept_map = [];
    $existing = $pdo->query('SELECT id, nama_departemen FROM departments')->fetchAll();
    foreach ($existing as $d) {
        $dept_map[mb_strtolower(trim($d['nama_departemen']))] = (int) $d['id'];
    }
    foreach ($hasil['employees'] as $emp) {
        $key = mb_strtolower(trim($emp['dept']));
        if ($key !== '' && !isset($dept_map[$key])) {
            $pdo->prepare('INSERT INTO departments (nama_departemen) VALUES (?)')->execute([$emp['dept']]);
            $dept_map[$key] = (int) $pdo->lastInsertId();
        }
    }

    // Cocokkan device_user_id dengan karyawan yang sudah ada.
    $employee_by_device = [];
    foreach ($pdo->query('SELECT id, device_user_id FROM employees WHERE device_user_id IS NOT NULL')->fetchAll() as $e) {
        $employee_by_device[(string) $e['device_user_id']] = (int) $e['id'];
    }

    $batch_id = uniqid('imp_', true);
    $stmtIns = $pdo->prepare(
        'INSERT INTO import_staging (batch_id, device_user_id, nama_file, dept_file, tanggal, jam_raw, jumlah_scan, employee_id)
         VALUES (?,?,?,?,?,?,?,?)'
    );

    foreach ($hasil['scans'] as $no => $hari_list) {
        $emp = $hasil['employees'][$no];
        $employee_id = $employee_by_device[$no] ?? null;
        foreach ($hari_list as $tanggal => $info) {
            $stmtIns->execute([
                $batch_id, $no, $emp['nama'], $emp['dept'], $tanggal,
                implode(',', $info['jam']), $info['jumlah_scan'], $employee_id,
            ]);
        }
    }

    $_SESSION['import_batch_id'] = $batch_id;
    $_SESSION['import_periode'] = $hasil['periode_awal'] . ' s/d ' . $hasil['periode_akhir'];
    $_SESSION['import_dept_map'] = $dept_map;

    // Ada yang belum ke-mapping ke karyawan?
    $cek = $pdo->prepare('SELECT COUNT(DISTINCT device_user_id) FROM import_staging WHERE batch_id = ? AND employee_id IS NULL');
    $cek->execute([$batch_id]);
    if ((int) $cek->fetchColumn() > 0) {
        redirect('import.php?step=mapping');
    }
    redirect('import.php?step=preview');
}

// =====================================================================
// STEP: apply_mapping (POST)
// =====================================================================
if ($step === 'apply_mapping' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = $_SESSION['import_batch_id'] ?? null;
    if (!$batch_id) {
        redirect('import.php');
    }

    $pilihan = $_POST['pilihan'] ?? []; // [no => 'baru'|'hubung'|'abaikan']
    $hubung_ke = $_POST['hubung_ke'] ?? []; // [no => employee_id]
    $shift_baru = $_POST['shift_baru'] ?? []; // [no => shift_id]
    $dept_map = $_SESSION['import_dept_map'] ?? [];

    $stmtInfo = $pdo->prepare('SELECT nama_file, dept_file FROM import_staging WHERE batch_id = ? AND device_user_id = ? LIMIT 1');
    $stmtSetEmp = $pdo->prepare('UPDATE import_staging SET employee_id = ? WHERE batch_id = ? AND device_user_id = ?');
    $stmtAbaikan = $pdo->prepare('UPDATE import_staging SET diabaikan = 1 WHERE batch_id = ? AND device_user_id = ?');

    foreach ($pilihan as $no => $aksi) {
        if ($aksi === 'abaikan') {
            $stmtAbaikan->execute([$batch_id, $no]);
            continue;
        }

        if ($aksi === 'hubung' && !empty($hubung_ke[$no])) {
            $employee_id = (int) $hubung_ke[$no];
            $pdo->prepare('UPDATE employees SET device_user_id = ? WHERE id = ?')->execute([$no, $employee_id]);
            $stmtSetEmp->execute([$employee_id, $batch_id, $no]);
            continue;
        }

        // default: buat karyawan baru
        $stmtInfo->execute([$batch_id, $no]);
        $info = $stmtInfo->fetch();
        if (!$info) {
            continue;
        }
        $dept_id = $dept_map[mb_strtolower(trim($info['dept_file'] ?? ''))] ?? null;
        $shift_id = !empty($shift_baru[$no]) ? (int) $shift_baru[$no] : null;

        $pdo->prepare('INSERT INTO employees (nik, nama, departemen_id, shift_id, device_user_id, status_aktif) VALUES (?,?,?,?,?,1)')
            ->execute([$no, $info['nama_file'], $dept_id, $shift_id, $no]);
        $employee_id = (int) $pdo->lastInsertId();
        $stmtSetEmp->execute([$employee_id, $batch_id, $no]);
    }

    redirect('import.php?step=preview');
}

// =====================================================================
// STEP: commit (POST) — simpan hasil final ke attendance_daily
// =====================================================================
if ($step === 'commit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = $_SESSION['import_batch_id'] ?? null;
    if (!$batch_id) {
        redirect('import.php');
    }

    $data = ambil_data_preview($pdo, $batch_id);
    $jumlah_disimpan = 0;
    $jumlah_tinjau = 0;

    foreach ($data as $row) {
        // Simpan juga ke log mentah untuk jejak audit.
        $stmtRaw = $pdo->prepare('SELECT jam_raw FROM import_staging WHERE batch_id = ? AND employee_id = ? AND tanggal = ?');
        $stmtRaw->execute([$batch_id, $row['employee_id'], $row['tanggal']]);
        $jam_raw = $stmtRaw->fetchColumn();
        if ($jam_raw) {
            foreach (explode(',', $jam_raw) as $jam) {
                $pdo->prepare('INSERT INTO attendance_raw_logs (device_user_id, scan_time, verify_type, is_processed) VALUES ((SELECT device_user_id FROM employees WHERE id = ?), ?, ?, 1)')
                    ->execute([$row['employee_id'], $row['tanggal'] . ' ' . $jam . ':00', 'import_excel']);
            }
        }

        $catatan = !empty($row['ambigu_deteksi'])
            ? 'Jam ditebak / shift dideteksi otomatis dari jam check-in — mohon dicek.'
            : null;

        try {
            $pdo->prepare(
                "INSERT INTO attendance_daily (employee_id, tanggal, jam_masuk_aktual, jam_pulang_aktual, status, menit_telat, jam_lembur, perlu_tinjau, sumber, catatan)
                 VALUES (?,?,?,?,?,?,?,?,'import',?)
                 ON DUPLICATE KEY UPDATE jam_masuk_aktual=VALUES(jam_masuk_aktual), jam_pulang_aktual=VALUES(jam_pulang_aktual),
                    status=VALUES(status), menit_telat=VALUES(menit_telat), jam_lembur=VALUES(jam_lembur), perlu_tinjau=VALUES(perlu_tinjau), sumber='import', catatan=VALUES(catatan)"
            )->execute([
                $row['employee_id'], $row['tanggal'], $row['jam_masuk'], $row['jam_pulang'],
                $row['status'], $row['menit_telat'], $row['jam_lembur'], $row['perlu_tinjau'] ? 1 : 0,
                $catatan,
            ]);
        } catch (PDOException $e) {
            // Fallback non-MySQL (dev/testing dengan SQLite).
            $cek = $pdo->prepare('SELECT id FROM attendance_daily WHERE employee_id=? AND tanggal=?');
            $cek->execute([$row['employee_id'], $row['tanggal']]);
            $ada = $cek->fetch();
            if ($ada) {
                $pdo->prepare("UPDATE attendance_daily SET jam_masuk_aktual=?, jam_pulang_aktual=?, status=?, menit_telat=?, jam_lembur=?, perlu_tinjau=?, sumber='import', catatan=? WHERE id=?")
                    ->execute([$row['jam_masuk'], $row['jam_pulang'], $row['status'], $row['menit_telat'], $row['jam_lembur'], $row['perlu_tinjau'] ? 1 : 0, $catatan, $ada['id']]);
            } else {
                $pdo->prepare("INSERT INTO attendance_daily (employee_id, tanggal, jam_masuk_aktual, jam_pulang_aktual, status, menit_telat, jam_lembur, perlu_tinjau, sumber, catatan) VALUES (?,?,?,?,?,?,?,?,'import',?)")
                    ->execute([$row['employee_id'], $row['tanggal'], $row['jam_masuk'], $row['jam_pulang'], $row['status'], $row['menit_telat'], $row['jam_lembur'], $row['perlu_tinjau'] ? 1 : 0, $catatan]);
            }
        }

        $jumlah_disimpan++;
        if ($row['perlu_tinjau']) {
            $jumlah_tinjau++;
        }
    }

    $pdo->prepare('DELETE FROM import_staging WHERE batch_id = ?')->execute([$batch_id]);
    unset($_SESSION['import_batch_id'], $_SESSION['import_periode'], $_SESSION['import_dept_map']);

    flash('success', "Import selesai! $jumlah_disimpan hari data tersimpan" . ($jumlah_tinjau ? ", $jumlah_tinjau di antaranya perlu ditinjau (cek Log Absensi)." : '.'));
    redirect('absensi.php');
}

// =====================================================================
// STEP: batal — bersihkan staging & session
// =====================================================================
if ($step === 'batal') {
    $batch_id = $_SESSION['import_batch_id'] ?? null;
    if ($batch_id) {
        $pdo->prepare('DELETE FROM import_staging WHERE batch_id = ?')->execute([$batch_id]);
    }
    unset($_SESSION['import_batch_id'], $_SESSION['import_periode'], $_SESSION['import_dept_map']);
    flash('success', 'Proses import dibatalkan.');
    redirect('import.php');
}

// =====================================================================
// Siapkan data untuk view (mapping / preview)
// =====================================================================
$unmapped = [];
$preview_data = [];
$ringkasan_preview = ['total' => 0, 'tinjau' => 0, 'karyawan' => 0];
$kandidat_karyawan = [];

if ($step === 'mapping') {
    $batch_id = $_SESSION['import_batch_id'] ?? null;
    if (!$batch_id) {
        redirect('import.php');
    }
    $stmt = $pdo->prepare('SELECT DISTINCT device_user_id, nama_file, dept_file FROM import_staging WHERE batch_id = ? AND employee_id IS NULL ORDER BY nama_file');
    $stmt->execute([$batch_id]);
    $unmapped = $stmt->fetchAll();

    $kandidat_karyawan = $pdo->query('SELECT id, nama, nik FROM employees WHERE device_user_id IS NULL ORDER BY nama')->fetchAll();
    $shift_list = $pdo->query('SELECT * FROM shifts ORDER BY nama_shift')->fetchAll();
}

if ($step === 'preview') {
    $batch_id = $_SESSION['import_batch_id'] ?? null;
    if (!$batch_id) {
        redirect('import.php');
    }
    $preview_data = ambil_data_preview($pdo, $batch_id);
    $ringkasan_preview['total'] = count($preview_data);
    $ringkasan_preview['tinjau'] = count(array_filter($preview_data, fn($r) => $r['perlu_tinjau']));
    $ringkasan_preview['karyawan'] = count(array_unique(array_column($preview_data, 'employee_id')));

    $hanya_tinjau_preview = !empty($_GET['hanya_tinjau']);

    if ($hanya_tinjau_preview) {
        $preview_data = array_values(array_filter($preview_data, fn($r) => $r['perlu_tinjau']));
    }

    // Pagination sederhana di sisi PHP (data preview belum masuk
    // database, jadi tidak bisa pakai LIMIT/OFFSET query).
    $per_halaman_preview = 50;
    $total_preview_tersaring = count($preview_data);
    $total_halaman_preview = max(1, (int) ceil($total_preview_tersaring / $per_halaman_preview));

    $halaman_preview = (int) ($_GET['halaman'] ?? 1);
    if ($halaman_preview < 1) {
        $halaman_preview = 1;
    }
    if ($halaman_preview > $total_halaman_preview) {
        $halaman_preview = $total_halaman_preview;
    }

    $preview_data_slice = array_slice(
        $preview_data,
        ($halaman_preview - 1) * $per_halaman_preview,
        $per_halaman_preview
    );

    // Query string dasar (tanpa halaman) untuk link toggle & pagination.
    $qs_preview_dasar = http_build_query([
        'step' => 'preview',
        'hanya_tinjau' => $hanya_tinjau_preview ? 1 : '',
    ]);
}

$page_title = 'Import Absensi';
$nav_active = 'import';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Import Absensi dari Excel</h1>
    <p class="page__description">Unggah file laporan absensi (.xls) hasil export dari mesin fingerprint lewat flashdisk. Shift setiap baris dideteksi otomatis dari jam check-in-nya.</p>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <?php if ($step === 'upload'): ?>
    <section class="page__section">
      <div class="card">
        <div class="card__body">
          <form method="post" enctype="multipart/form-data" class="flex flex-col gap-4">
            <div class="field">
              <label class="field__label">File Excel dari mesin (.xls)</label>
              <input type="file" name="file_excel" class="input" accept=".xls,.xlsx" required>
              <span class="field__description">Ambil file ini dari flashdisk hasil export mesin, jangan diedit dulu manual.</span>
            </div>
            <div class="alert alert--neutral">
              <?= ic('solar:danger-triangle-bold-duotone') ?>
              <span class="text-sm">Sistem akan membaca sheet "Ringkasan" dan "Catatan" dari file tersebut. Karyawan yang belum dikenali akan diminta untuk dipetakan dulu sebelum data disimpan.</span>
            </div>
            <div>
              <button type="submit" class="button button--primary"><?= ic('solar:upload-linear') ?> Unggah &amp; Proses</button>
            </div>
          </form>
        </div>
      </div>
    </section>

  <?php elseif ($step === 'mapping'): ?>
    <section class="page__section">
      <div class="card">
        <div class="card__header">
          <span class="card__title">Pemetaan Karyawan Baru</span>
        </div>
        <div class="card__body">
          <p class="text-muted-foreground mb-4">Ditemukan <?= count($unmapped) ?> ID mesin yang belum dikenali sistem. Tentukan untuk masing-masing:</p>
          <form method="post" action="import.php?step=apply_mapping" class="flex flex-col gap-6">
            <?php foreach ($unmapped as $u): $no = e($u['device_user_id']); ?>
              <div class="card card--outline">
                <div class="card__body">
                  <p class="font-medium mb-2"><?= e($u['nama_file']) ?> <span class="text-muted-foreground text-sm">(ID Mesin: <?= $no ?>, Dept file: <?= e($u['dept_file']) ?>)</span></p>
                  <div class="flex flex-col gap-2">
                    <label class="field__item">
                      <input type="radio" class="radio" name="pilihan[<?= $no ?>]" value="baru" checked>
                      <span class="field__label">Buat karyawan baru, shift:</span>
                    </label>
                    <select name="shift_baru[<?= $no ?>]" class="select ms-6" style="max-width:20rem" required>
                      <option value="">— pilih shift —</option>
                      <?php foreach ($shift_list as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['nama_shift']) ?> (<?= jam($s['jam_masuk']) ?>–<?= jam($s['jam_pulang']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <label class="field__item">
                      <input type="radio" class="radio" name="pilihan[<?= $no ?>]" value="hubung">
                      <span class="field__label">Hubungkan ke karyawan yang sudah ada:</span>
                    </label>
                    <select name="hubung_ke[<?= $no ?>]" class="select ms-6" style="max-width:20rem">
                      <option value="">— pilih karyawan —</option>
                      <?php foreach ($kandidat_karyawan as $k): ?>
                        <option value="<?= (int) $k['id'] ?>"><?= e($k['nama']) ?> (<?= e($k['nik']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <label class="field__item">
                      <input type="radio" class="radio" name="pilihan[<?= $no ?>]" value="abaikan">
                      <span class="field__label">Abaikan (jangan import datanya)</span>
                    </label>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="flex gap-2">
              <a href="import.php?step=batal" class="button button--ghost button--neutral">Batal Import</a>
              <button type="submit" class="button button--primary">Lanjut ke Pratinjau</button>
            </div>
          </form>
        </div>
      </div>
    </section>

  <?php elseif ($step === 'preview'): ?>
    <section class="page__section">
      <div style="display:flex; flex-wrap:wrap; gap:16px;">

        <div style="flex:1 1 220px;">
          <div class="card" style="height:100%;">
            <div class="card__body" style="display:flex; align-items:center; gap:16px;">
              <div style="width:48px; height:48px; border-radius:12px; background:rgba(99,102,241,.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.25rem;">
                <?= ic('solar:checklist-minimalistic-bold-duotone') ?>
              </div>
              <div>
                <div style="font-size:1.75rem; font-weight:700; line-height:1.1;"><?= $ringkasan_preview['total'] ?></div>
                <div class="text-xs text-muted-foreground" style="margin-top:2px;">Total Baris Data</div>
              </div>
            </div>
          </div>
        </div>

        <div style="flex:1 1 220px;">
          <div class="card" style="height:100%;">
            <div class="card__body" style="display:flex; align-items:center; gap:16px;">
              <div style="width:48px; height:48px; border-radius:12px; background:rgba(16,185,129,.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.25rem;">
                <?= ic('solar:users-group-rounded-bold-duotone') ?>
              </div>
              <div>
                <div style="font-size:1.75rem; font-weight:700; line-height:1.1;"><?= $ringkasan_preview['karyawan'] ?></div>
                <div class="text-xs text-muted-foreground" style="margin-top:2px;">Karyawan Terlibat</div>
              </div>
            </div>
          </div>
        </div>

        <div style="flex:1 1 220px;">
          <div class="card" style="height:100%;">
            <div class="card__body" style="display:flex; align-items:center; gap:16px;">
              <div style="width:48px; height:48px; border-radius:12px; background:<?= $ringkasan_preview['tinjau'] > 0 ? 'rgba(217,119,6,.12)' : 'rgba(148,163,184,.12)' ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.25rem;">
                <?= ic('solar:danger-triangle-bold-duotone') ?>
              </div>
              <div>
                <div style="font-size:1.75rem; font-weight:700; line-height:1.1; <?= $ringkasan_preview['tinjau'] > 0 ? 'color:#d97706;' : '' ?>"><?= $ringkasan_preview['tinjau'] ?></div>
                <div class="text-xs text-muted-foreground" style="margin-top:2px;">Perlu Ditinjau</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <section class="page__section">
      <div class="card">
        <div class="card__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <span class="card__title">Pratinjau Data</span>
            <div class="text-xs text-muted-foreground" style="margin-top:4px;">
              Menampilkan <?= count($preview_data_slice) ?> dari <?= $total_preview_tersaring ?> baris
              (halaman <?= $halaman_preview ?> dari <?= $total_halaman_preview ?>)
            </div>
          </div>
          <div class="card__action">
            <a href="?step=preview<?= $hanya_tinjau_preview ? '' : '&hanya_tinjau=1' ?>" class="button button--sm button--ghost button--neutral">
              <?= $hanya_tinjau_preview ? 'Tampilkan semua' : 'Tampilkan yang perlu ditinjau saja' ?>
            </a>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table--hover table--align-middle">
            <thead class="table__head--alt">
              <tr>
                <th>Tanggal</th><th>Karyawan</th><th>Dept</th><th>Masuk</th><th>Pulang</th><th>Shift Terdeteksi</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview_data_slice as $r): ?>
                <tr>
                  <td><?= e(tgl_indo($r['tanggal'], false)) ?></td>
                  <td><?= e($r['nama']) ?></td>
                  <td><?= e($r['departemen'] ?? '-') ?></td>
                  <td><?= jam($r['jam_masuk']) ?></td>
                  <td><?= jam($r['jam_pulang']) ?></td>
                  <td><?= e($r['shift_terdeteksi'] ?? '-') ?></td>
                  <td>
                    <?= badge_status($r['status']) ?>
                    <?php if (!empty($r['jam_lembur'])): ?>
                      <span class="badge badge--soft badge--primary">Lembur <?= rtrim(rtrim(number_format((float) $r['jam_lembur'], 2), '0'), '.') ?> jam</span>
                    <?php endif; ?>
                  </td>
                  <td><?= $r['perlu_tinjau'] ? '<span class="badge badge--soft badge--warning">Perlu Ditinjau</span>' : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($total_halaman_preview > 1): ?>
          <div class="card__footer" style="display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; padding:16px;">

            <a href="?<?= e($qs_preview_dasar) ?>&halaman=<?= max(1, $halaman_preview - 1) ?>"
               class="button button--sm button--ghost button--neutral"
               <?= $halaman_preview <= 1 ? 'aria-disabled="true" tabindex="-1" style="pointer-events:none; opacity:.5;"' : '' ?>>
              <?= ic('solar:alt-arrow-left-linear') ?> Sebelumnya
            </a>

            <?php
              $mulai_p = max(1, $halaman_preview - 2);
              $selesai_p = min($total_halaman_preview, $mulai_p + 4);
              $mulai_p = max(1, $selesai_p - 4);
            ?>

            <?php if ($mulai_p > 1): ?>
              <a href="?<?= e($qs_preview_dasar) ?>&halaman=1" class="button button--sm button--ghost button--neutral">1</a>
              <?php if ($mulai_p > 2): ?><span class="text-muted-foreground">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $mulai_p; $p <= $selesai_p; $p++): ?>
              <a href="?<?= e($qs_preview_dasar) ?>&halaman=<?= $p ?>"
                 class="button button--sm <?= $p === $halaman_preview ? 'button--primary' : 'button--ghost button--neutral' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>

            <?php if ($selesai_p < $total_halaman_preview): ?>
              <?php if ($selesai_p < $total_halaman_preview - 1): ?><span class="text-muted-foreground">…</span><?php endif; ?>
              <a href="?<?= e($qs_preview_dasar) ?>&halaman=<?= $total_halaman_preview ?>" class="button button--sm button--ghost button--neutral"><?= $total_halaman_preview ?></a>
            <?php endif; ?>

            <a href="?<?= e($qs_preview_dasar) ?>&halaman=<?= min($total_halaman_preview, $halaman_preview + 1) ?>"
               class="button button--sm button--ghost button--neutral"
               <?= $halaman_preview >= $total_halaman_preview ? 'aria-disabled="true" tabindex="-1" style="pointer-events:none; opacity:.5;"' : '' ?>>
              Berikutnya <?= ic('solar:alt-arrow-right-linear') ?>
            </a>

          </div>
        <?php endif; ?>

      </div>
    </section>

    <form method="post" action="import.php?step=commit" class="flex gap-2">
      <a href="import.php?step=batal" class="button button--ghost button--neutral">Batal</a>
      <button type="submit" class="button button--primary">Simpan ke Database</button>
    </form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>