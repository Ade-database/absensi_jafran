<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$hari_opsi = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => "Jum'at", 6 => 'Sabtu', 7 => 'Minggu'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama = trim($_POST['nama_shift'] ?? '');
        $jam_masuk = $_POST['jam_masuk'] ?? '';
        $jam_pulang = $_POST['jam_pulang'] ?? '';
        $toleransi = (int) ($_POST['toleransi_menit'] ?? 15);
        $is_lembur = isset($_POST['is_lembur']) ? 1 : 0;
        $hari_terpilih = $_POST['hari_kerja'] ?? [];
        $hari_kerja = implode(',', array_map('intval', $hari_terpilih));
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($nama === '' || $jam_masuk === '' || $jam_pulang === '' || empty($hari_terpilih)) {
            flash('error', 'Nama shift, jam kerja, dan minimal 1 hari kerja wajib diisi.');
        } elseif ($aksi === 'tambah') {
            $pdo->prepare('INSERT INTO shifts (nama_shift, jam_masuk, jam_pulang, toleransi_menit, is_lembur, hari_kerja, keterangan) VALUES (?,?,?,?,?,?,?)')
                ->execute([$nama, $jam_masuk, $jam_pulang, $toleransi, $is_lembur, $hari_kerja, $keterangan]);
            flash('success', 'Shift berhasil ditambahkan.');
        } else {
            $id = (int) $_POST['id'];
            $pdo->prepare('UPDATE shifts SET nama_shift=?, jam_masuk=?, jam_pulang=?, toleransi_menit=?, is_lembur=?, hari_kerja=?, keterangan=? WHERE id=?')
                ->execute([$nama, $jam_masuk, $jam_pulang, $toleransi, $is_lembur, $hari_kerja, $keterangan, $id]);
            flash('success', 'Shift berhasil diperbarui.');
        }
    } elseif ($aksi === 'hapus') {
        $id = (int) $_POST['id'];
        $cek = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE shift_id = ?');
        $cek->execute([$id]);
        if ((int) $cek->fetchColumn() > 0) {
            flash('error', 'Tidak bisa menghapus, masih ada karyawan yang memakai shift ini.');
        } else {
            $pdo->prepare('DELETE FROM shifts WHERE id = ?')->execute([$id]);
            flash('success', 'Shift berhasil dihapus.');
        }
    }
    redirect('shift.php');
}

$shifts = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM employees e WHERE e.shift_id = s.id) AS jumlah_karyawan
     FROM shifts s ORDER BY s.nama_shift'
)->fetchAll();

$page_title = 'Shift Kerja';
$nav_active = 'shift';
include __DIR__ . '/includes/header.php';

function form_shift_fields(array $hari_opsi, ?array $data = null): void
{
    $data = $data ?? ['nama_shift' => '', 'jam_masuk' => '08:00', 'jam_pulang' => '17:00', 'toleransi_menit' => 15, 'is_lembur' => 0, 'hari_kerja' => '1,2,3,4,5', 'keterangan' => ''];
    $hari_terpilih = array_map('trim', explode(',', $data['hari_kerja']));
    ?>
    <div class="field">
      <label class="field__label">Nama Shift</label>
      <input type="text" name="nama_shift" class="input" value="<?= e($data['nama_shift']) ?>" placeholder="mis: Shift Kantor" required>
    </div>
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-6">
        <div class="field">
          <label class="field__label">Jam Masuk</label>
          <input type="time" name="jam_masuk" class="input" value="<?= e(substr($data['jam_masuk'], 0, 5)) ?>" required>
        </div>
      </div>
      <div class="col-span-6">
        <div class="field">
          <label class="field__label">Jam Pulang</label>
          <input type="time" name="jam_pulang" class="input" value="<?= e(substr($data['jam_pulang'], 0, 5)) ?>" required>
        </div>
      </div>
    </div>
    <div class="field">
      <label class="field__label">Toleransi Telat (menit)</label>
      <input type="number" name="toleransi_menit" class="input" min="0" value="<?= (int) $data['toleransi_menit'] ?>">
    </div>
    <div class="field">
      <label class="field__label">Hari Kerja</label>
      <div class="flex flex-wrap gap-3">
        <?php foreach ($hari_opsi as $angka => $label): ?>
          <label class="field__item">
            <input type="checkbox" class="checkbox" name="hari_kerja[]" value="<?= $angka ?>" <?= in_array((string) $angka, $hari_terpilih, true) ? 'checked' : '' ?>>
            <span class="field__label"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field__item">
      <input type="checkbox" class="checkbox" name="is_lembur" id="is_lembur<?= e($data['nama_shift']) ?>" <?= $data['is_lembur'] ? 'checked' : '' ?>>
      <label class="field__label" for="is_lembur<?= e($data['nama_shift']) ?>">Tandai sebagai shift lembur/malam</label>
    </div>
    <div class="field">
      <label class="field__label">Keterangan (opsional)</label>
      <textarea name="keterangan" class="textarea" rows="2"><?= e($data['keterangan']) ?></textarea>
    </div>
    <?php
}
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Shift Kerja</h1>
    <p class="page__description">Atur jam masuk, jam pulang, dan hari kerja untuk tiap pola shift.</p>
  </div>
  <div class="page__action">
    <button type="button" class="button button--primary" data-stisla-dialog-trigger="tambahShift">
      <?= ic('solar:add-circle-linear') ?> Tambah Shift
    </button>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <div class="card">
      <?php if (empty($shifts)): ?>
        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:clock-circle-bold-duotone') ?></div>
          <h3 class="empty-state__title">Belum ada shift</h3>
          <p class="empty-state__text">Tambahkan pola shift kerja, misalnya "Shift Kantor" 08:00–17:00.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table--hover table--align-middle">
            <thead class="table__head--alt">
              <tr>
                <th>Nama Shift</th>
                <th>Jam Kerja</th>
                <th>Toleransi</th>
                <th>Hari Kerja</th>
                <th>Tipe</th>
                <th>Karyawan</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shifts as $s): ?>
                <?php
                  $hari_label = implode(', ', array_map(fn($h) => $hari_opsi[(int)$h] ?? $h, explode(',', $s['hari_kerja'])));
                ?>
                <tr>
                  <td class="font-medium"><?= e($s['nama_shift']) ?></td>
                  <td><?= jam($s['jam_masuk']) ?> – <?= jam($s['jam_pulang']) ?></td>
                  <td><?= (int) $s['toleransi_menit'] ?> menit</td>
                  <td class="text-sm text-muted-foreground"><?= e($hari_label) ?></td>
                  <td><?= $s['is_lembur'] ? '<span class="badge badge--soft badge--primary">Lembur</span>' : '<span class="badge badge--soft badge--neutral">Normal</span>' ?></td>
                  <td><?= (int) $s['jumlah_karyawan'] ?> orang</td>
                  <td class="text-end">
                    <button type="button" class="button button--sm button--ghost button--neutral" data-stisla-dialog-trigger="editShift<?= (int) $s['id'] ?>"><?= ic('solar:pen-2-linear') ?></button>
                    <button type="button" class="button button--sm button--ghost button--danger" data-stisla-dialog-trigger="hapusShift<?= (int) $s['id'] ?>"><?= ic('solar:trash-bin-minimalistic-linear') ?></button>
                  </td>
                </tr>

                <div class="dialog" id="editShift<?= (int) $s['id'] ?>" data-stisla-dialog data-state="closed" role="dialog" aria-modal="true" tabindex="-1">
                  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
                  <div class="dialog__panel">
                    <div class="dialog__content">
                      <form method="post">
                        <input type="hidden" name="aksi" value="edit">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
                        <div class="dialog__header"><h3 class="dialog__title">Edit Shift</h3></div>
                        <div class="dialog__body flex flex-col gap-4">
                          <?php form_shift_fields($hari_opsi, $s); ?>
                        </div>
                        <div class="dialog__footer">
                          <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
                          <button type="submit" class="button button--primary">Simpan</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <div class="dialog dialog--sm" id="hapusShift<?= (int) $s['id'] ?>" data-stisla-dialog data-state="closed" role="alertdialog" aria-modal="true" tabindex="-1">
                  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
                  <div class="dialog__panel">
                    <div class="dialog__content">
                      <form method="post">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
                        <div class="dialog__header"><h3 class="dialog__title">Hapus Shift?</h3></div>
                        <div class="dialog__body"><p class="text-muted-foreground">Yakin ingin menghapus <strong><?= e($s['nama_shift']) ?></strong>?</p></div>
                        <div class="dialog__footer">
                          <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
                          <button type="submit" class="button button--danger">Hapus</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="dialog" id="tambahShift" data-stisla-dialog data-state="closed" role="dialog" aria-modal="true" tabindex="-1">
  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
  <div class="dialog__panel">
    <div class="dialog__content">
      <form method="post">
        <input type="hidden" name="aksi" value="tambah">
        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
        <div class="dialog__header"><h3 class="dialog__title">Tambah Shift</h3></div>
        <div class="dialog__body flex flex-col gap-4">
          <?php form_shift_fields($hari_opsi); ?>
        </div>
        <div class="dialog__footer">
          <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
          <button type="submit" class="button button--primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
