<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama = trim($_POST['nama_departemen'] ?? '');
        if ($nama === '') {
            flash('error', 'Nama departemen wajib diisi.');
        } elseif ($aksi === 'tambah') {
            $pdo->prepare('INSERT INTO departments (nama_departemen) VALUES (?)')->execute([$nama]);
            flash('success', 'Departemen berhasil ditambahkan.');
        } else {
            $id = (int) $_POST['id'];
            $pdo->prepare('UPDATE departments SET nama_departemen = ? WHERE id = ?')->execute([$nama, $id]);
            flash('success', 'Departemen berhasil diperbarui.');
        }
    } elseif ($aksi === 'hapus') {
        $id = (int) $_POST['id'];
        $cek = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE departemen_id = ?');
        $cek->execute([$id]);
        if ((int) $cek->fetchColumn() > 0) {
            flash('error', 'Tidak bisa menghapus, masih ada karyawan di departemen ini.');
        } else {
            $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
            flash('success', 'Departemen berhasil dihapus.');
        }
    }
    redirect('departemen.php');
}

$departemen = $pdo->query(
    'SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.departemen_id = d.id) AS jumlah_karyawan
     FROM departments d ORDER BY d.nama_departemen'
)->fetchAll();

$page_title = 'Departemen';
$nav_active = 'departemen';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Departemen</h1>
    <p class="page__description">Kelola daftar departemen/divisi perusahaan.</p>
  </div>
  <div class="page__action">
    <button type="button" class="button button--primary" data-stisla-dialog-trigger="tambahDept">
      <?= ic('solar:add-circle-linear') ?> Tambah Departemen
    </button>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <div class="card">
      <?php if (empty($departemen)): ?>
        <div class="empty-state empty-state--sm">
          <div class="empty-state__media"><?= ic('solar:buildings-2-bold-duotone') ?></div>
          <h3 class="empty-state__title">Belum ada departemen</h3>
          <p class="empty-state__text">Tambahkan departemen pertama, misalnya "Kantor" atau "Processing".</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table--hover table--align-middle">
            <thead class="table__head--alt">
              <tr>
                <th>Nama Departemen</th>
                <th>Jumlah Karyawan</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departemen as $d): ?>
                <tr>
                  <td class="font-medium"><?= e($d['nama_departemen']) ?></td>
                  <td><?= (int) $d['jumlah_karyawan'] ?> orang</td>
                  <td class="text-end">
                    <button type="button" class="button button--sm button--ghost button--neutral"
                            data-stisla-dialog-trigger="editDept<?= (int) $d['id'] ?>">
                      <?= ic('solar:pen-2-linear') ?>
                    </button>
                    <button type="button" class="button button--sm button--ghost button--danger"
                            data-stisla-dialog-trigger="hapusDept<?= (int) $d['id'] ?>">
                      <?= ic('solar:trash-bin-minimalistic-linear') ?>
                    </button>
                  </td>
                </tr>

                <!-- Dialog edit -->
                <div class="dialog" id="editDept<?= (int) $d['id'] ?>" data-stisla-dialog data-state="closed" role="dialog" aria-modal="true" tabindex="-1">
                  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
                  <div class="dialog__panel">
                    <div class="dialog__content">
                      <form method="post">
                        <input type="hidden" name="aksi" value="edit">
                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
                        <div class="dialog__header"><h3 class="dialog__title">Edit Departemen</h3></div>
                        <div class="dialog__body flex flex-col gap-4">
                          <div class="field">
                            <label class="field__label">Nama Departemen</label>
                            <input type="text" name="nama_departemen" class="input" value="<?= e($d['nama_departemen']) ?>" required>
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

                <!-- Dialog hapus -->
                <div class="dialog dialog--sm" id="hapusDept<?= (int) $d['id'] ?>" data-stisla-dialog data-state="closed" role="alertdialog" aria-modal="true" tabindex="-1">
                  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
                  <div class="dialog__panel">
                    <div class="dialog__content">
                      <form method="post">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
                        <div class="dialog__header"><h3 class="dialog__title">Hapus Departemen?</h3></div>
                        <div class="dialog__body">
                          <p class="text-muted-foreground">Yakin ingin menghapus <strong><?= e($d['nama_departemen']) ?></strong>? Tindakan ini tidak bisa dibatalkan.</p>
                        </div>
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

<!-- Dialog tambah -->
<div class="dialog" id="tambahDept" data-stisla-dialog data-state="closed" role="dialog" aria-modal="true" tabindex="-1">
  <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
  <div class="dialog__panel">
    <div class="dialog__content">
      <form method="post">
        <input type="hidden" name="aksi" value="tambah">
        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
        <div class="dialog__header"><h3 class="dialog__title">Tambah Departemen</h3></div>
        <div class="dialog__body flex flex-col gap-4">
          <div class="field">
            <label class="field__label">Nama Departemen</label>
            <input type="text" name="nama_departemen" class="input" placeholder="mis: Kantor" required autofocus>
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

<?php include __DIR__ . '/includes/footer.php'; ?>
