<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nama'] = $admin['nama'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect('index.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Masuk · Absensi PT Jafran Indonesia</title>
  <script>
    (function () {
      var t = localStorage.getItem('stisla-theme');
      if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <main class="auth">
    <section class="auth__panel">
      <button type="button" class="button button--ghost button--neutral button--icon-only auth__toggle" data-theme-toggle aria-label="Ganti tema">
        <?= ic('solar:moon-linear') ?>
      </button>
      <div class="auth__form">
        <div>
          <h1 class="text-2xl">Selamat datang</h1>
          <p class="text-muted-foreground mt-1">Masuk ke dashboard Absensi PT Jafran Indonesia.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert--danger">
            <?= ic('solar:danger-triangle-bold-duotone') ?>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="flex flex-col gap-4">
          <div class="field">
            <label for="username" class="field__label">Username</label>
            <div class="input-group input-group--lg">
              <span class="input-group__text"><?= ic('solar:user-linear') ?></span>
              <input type="text" name="username" class="input" id="username" placeholder="admin" autofocus required />
            </div>
          </div>

          <div class="field">
            <label for="password" class="field__label">Password</label>
            <div class="input-group input-group--lg">
              <span class="input-group__text"><?= ic('solar:lock-keyhole-bold-duotone') ?></span>
              <input type="password" name="password" class="input" id="password" placeholder="••••••••••" required />
              <button type="button" class="input-group__text" data-password-toggle aria-controls="password" aria-label="Tampilkan password" aria-pressed="false">
                <?= ic('solar:eye-linear') ?>
              </button>
            </div>
          </div>

          <button type="submit" class="button button--primary button--block button--lg">Masuk</button>
        </form>
      </div>
    </section>

    <aside class="auth__aside">
      <a href="#" class="auth__brand">
        <span class="auth__brand-mark">
          <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1.5l3.4 7.1 7.1 3.4-7.1 3.4-3.4 7.1-3.4-7.1L1.5 12l7.1-3.4z" opacity=".45"/><path d="M12 1.5l3.4 7.1L12 12 8.6 8.6z"/></svg>
        </span>
        <span class="auth__brand-text">
          <span class="auth__brand-name">Absensi Jafran</span>
        </span>
      </a>
      <div class="auth__pitch">
        <h2 class="auth__pitch-title">Kelola absensi <span>lebih rapi & realtime.</span></h2>
        <p class="auth__pitch-lede">
          Pantau kehadiran, shift kerja, dan laporan karyawan PT Jafran Indonesia dari satu dashboard.
        </p>
      </div>
    </aside>
  </main>

  <script type="module" src="https://cdn.jsdelivr.net/npm/@stisla/vanilla@3/dist/stisla.js"></script>
  <script src="assets/js/theme.js"></script>
  <script>
    document.addEventListener('click', function (event) {
      var toggle = event.target.closest('[data-password-toggle]');
      if (!toggle) return;
      var input = document.getElementById(toggle.getAttribute('aria-controls'));
      if (!input) return;
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', String(reveal));
    });
  </script>
</body>
</html>
