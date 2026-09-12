<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$admin_id = (int) $_SESSION['admin_id'];
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'profil') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        if ($nama === '' || $username === '') {
            flash('error', 'Nama dan username wajib diisi.');
        } else {
            $pdo->prepare('UPDATE admins SET nama = ?, username = ? WHERE id = ?')->execute([$nama, $username, $admin_id]);
            $_SESSION['admin_nama'] = $nama;
            $_SESSION['admin_username'] = $username;
            flash('success', 'Profil berhasil diperbarui.');
        }
    } elseif ($aksi === 'password') {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        if (!password_verify($password_lama, $admin['password_hash'])) {
            flash('error', 'Password lama salah.');
        } elseif (strlen($password_baru) < 6) {
            flash('error', 'Password baru minimal 6 karakter.');
        } elseif ($password_baru !== $password_konfirmasi) {
            flash('error', 'Konfirmasi password baru tidak cocok.');
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password_baru, PASSWORD_BCRYPT), $admin_id]);
            flash('success', 'Password berhasil diganti.');
        }
    }
    redirect('pengaturan.php');
}

$page_title = 'Pengaturan';
$nav_active = 'pengaturan';
include __DIR__ . '/includes/header.php';
?>
<header class="page__header">
  <div class="page__headline">
    <h1 class="page__title">Pengaturan</h1>
    <p class="page__description">Kelola profil dan password akun admin.</p>
  </div>
</header>

<div class="page__body">
  <?php include __DIR__ . '/includes/flash.php'; ?>

  <section class="page__section">
    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <div class="card__header"><span class="card__title">Profil Admin</span></div>
          <div class="card__body">
            <form method="post" class="flex flex-col gap-4">
              <input type="hidden" name="aksi" value="profil">
              <div class="field">
                <label class="field__label">Nama</label>
                <input type="text" name="nama" class="input" value="<?= e($admin['nama']) ?>" required>
              </div>
              <div class="field">
                <label class="field__label">Username</label>
                <input type="text" name="username" class="input" value="<?= e($admin['username']) ?>" required>
              </div>
              <div>
                <button type="submit" class="button button--primary">Simpan Profil</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <div class="card__header"><span class="card__title">Ganti Password</span></div>
          <div class="card__body">
            <form method="post" class="flex flex-col gap-4">
              <input type="hidden" name="aksi" value="password">
              <div class="field">
                <label class="field__label">Password Lama</label>
                <input type="password" name="password_lama" class="input" required>
              </div>
              <div class="field">
                <label class="field__label">Password Baru</label>
                <input type="password" name="password_baru" class="input" minlength="6" required>
              </div>
              <div class="field">
                <label class="field__label">Konfirmasi Password Baru</label>
                <input type="password" name="password_konfirmasi" class="input" minlength="6" required>
              </div>
              <div>
                <button type="submit" class="button button--primary">Ganti Password</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
