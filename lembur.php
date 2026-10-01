<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

// =====================================================================
// Simpan pengaturan (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_menit_raw = trim($_POST['lembur_min_menit'] ?? '');
    $max_jam_raw = trim($_POST['lembur_max_jam'] ?? '');

    // Terima koma sebagai pemisah desimal (mis. 4,5).
    $max_jam_raw = str_replace(',', '.', $max_jam_raw);

    if (!ctype_digit($min_menit_raw)) {
        flash('error', 'Lembur minimal harus berupa angka menit (0 atau lebih).');
        redirect('lembur.php');
    }

    if ($max_jam_raw === '' || !is_numeric($max_jam_raw)) {
        flash('error', 'Lembur maksimal harus berupa angka jam. Isi 0 kalau tanpa batas.');
        redirect('lembur.php');
    }

    $min_menit = (int) $min_menit_raw;
    $max_jam = round((float) $max_jam_raw, 2);

    if ($min_menit > 600) {
        flash('error', 'Lembur minimal maksimal 600 menit (10 jam).');
        redirect('lembur.php');
    }

    if ($max_jam < 0 || $max_jam > 24) {
        flash('error', 'Lembur maksimal harus antara 0 sampai 24 jam.');
        redirect('lembur.php');
    }

    if ($max_jam > 0 && ($max_jam * 60) < $min_menit) {
        flash('error', 'Lembur maksimal tidak boleh lebih kecil dari lembur minimal.');
        redirect('lembur.php');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (kunci, nilai) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
        );
        $stmt->execute(['lembur_min_menit', (string) $min_menit]);
        $stmt->execute(['lembur_max_jam', (string) $max_jam]);
    } catch (\Throwable $e) {
        flash('error', 'Gagal menyimpan. Pastikan tabel "settings" sudah dibuat lewat SQL. Detail: ' . $e->getMessage());
        redirect('lembur.php');
    }

    flash('success', 'Aturan lembur disimpan. Berlaku untuk import berikutnya.');
    redirect('lembur.php');
}

$atur = ambil_pengaturan_lembur($pdo);

$page_title = 'Aturan Lembur';
$nav_active = 'lembur';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Aturan Lembur</h1>
    <p class="page__description">Atur batas minimal dan maksimal jam lembur yang dihitung sistem saat import absensi dari mesin.</p>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <div class="card">
      <div class="card__body">
        <form method="post" class="flex flex-col gap-4" style="max-width:32rem;">

          <div class="field">
            <label for="lembur_min_menit" class="field__label">Lembur minimal (menit)</label>
            <input type="number" min="0" max="600" step="1" class="input" id="lembur_min_menit" name="lembur_min_menit"
                   value="<?= (int) $atur['min_menit'] ?>" required>
            <span class="field__description">Pulang melewati jam selesai shift kurang dari angka ini tidak dihitung lembur. Contoh: 30 berarti pulang 15 menit lewat jadwal bukan lembur.</span>
          </div>

          <div class="field">
            <label for="lembur_max_jam" class="field__label">Lembur maksimal (jam per hari)</label>
            <input type="number" min="0" max="24" step="0.25" class="input" id="lembur_max_jam" name="lembur_max_jam"
                   value="<?= e(rtrim(rtrim(number_format((float) $atur['max_jam'], 2, '.', ''), '0'), '.') ?: '0') ?>" required>
            <span class="field__description">Lembur lebih dari angka ini dipotong dan diberi catatan di Log Absensi. Isi 0 kalau tanpa batas.</span>
          </div>

          <div class="alert alert--neutral">
            <?= ic('solar:danger-triangle-bold-duotone') ?>
            <span class="text-sm">Aturan ini berlaku untuk data yang diimport setelah disimpan. Data lama di Log Absensi tidak berubah.</span>
          </div>

          <div>
            <button type="submit" class="button button--primary">Simpan Aturan</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>