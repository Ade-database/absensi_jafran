<?php $admin = admin_sekarang(); ?>
<style>
  .sidebar__brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  .sidebar__brand .brand-logo {
    display: block;
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    max-width: 36px !important;
    padding: 4px;
    background: #ffffff;
    border-radius: 10px;
    object-fit: contain;
    flex-shrink: 0;
  }
</style>
<aside class="sidebar sidebar--lg sidebar--app" data-stisla-sidebar>
  <header class="sidebar__header">
    <a class="sidebar__brand" href="index.php">
      <img src="assets/img/logo_jafran.png" alt="Logo PT Jafran Indonesia" class="brand-logo" />
      <span>Absensi Jafran</span>
    </a>
  </header>

  <div class="sidebar__content">
    <nav class="sidebar__menu">
      <div class="sidebar__group">
        <span class="sidebar__group-title">Menu</span>
        <ul class="sidebar__list">
          <li class="sidebar__item">
            <a class="sidebar__button" href="index.php" <?= $nav_active === 'dashboard' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:widget-bold-duotone') ?><span>Dashboard</span>
            </a>
          </li>
        </ul>
      </div>

      <div class="sidebar__group">
        <span class="sidebar__group-title">Data Master</span>
        <ul class="sidebar__list">
          <li class="sidebar__item">
            <a class="sidebar__button" href="karyawan.php" <?= $nav_active === 'karyawan' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:users-group-rounded-bold-duotone') ?><span>Karyawan</span>
            </a>
          </li>
          <li class="sidebar__item">
            <a class="sidebar__button" href="departemen.php" <?= $nav_active === 'departemen' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:buildings-2-bold-duotone') ?><span>Departemen</span>
            </a>
          </li>
          <li class="sidebar__item">
            <a class="sidebar__button" href="shift.php" <?= $nav_active === 'shift' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:clock-circle-bold-duotone') ?><span>Shift Kerja</span>
            </a>
          </li>
          <li class="sidebar__item">
            <a class="sidebar__button" href="lembur.php" <?= $nav_active === 'lembur' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:settings-bold-duotone') ?><span>Aturan Lembur</span>
            </a>
          </li>
        </ul>
      </div>

      <div class="sidebar__group">
        <span class="sidebar__group-title">Absensi</span>
        <ul class="sidebar__list">
          <li class="sidebar__item">
            <a class="sidebar__button" href="absensi.php" <?= $nav_active === 'absensi' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:checklist-minimalistic-bold-duotone') ?><span>Log Absensi</span>
            </a>
          </li>
          <li class="sidebar__item">
            <a class="sidebar__button" href="import.php" <?= $nav_active === 'import' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:upload-linear') ?><span>Import Absensi</span>
            </a>
          </li>
          <li class="sidebar__item">
            <a class="sidebar__button" href="laporan.php" <?= $nav_active === 'laporan' ? 'aria-current="page"' : '' ?>>
              <?= ic('solar:chart-2-bold-duotone') ?><span>Laporan</span>
            </a>
          </li>
        </ul>
      </div>
    </nav>
  </div>

  <footer class="sidebar__footer">
    <ul class="sidebar__list">
      <li class="sidebar__item">
        <a class="sidebar__button" href="pengaturan.php" <?= $nav_active === 'pengaturan' ? 'aria-current="page"' : '' ?>>
          <?= ic('solar:settings-bold-duotone') ?><span>Pengaturan</span>
        </a>
      </li>
      <li class="sidebar__item">
        <button type="button" class="sidebar__button w-full text-start" data-stisla-dialog-trigger="logoutConfirm">
          <?= ic('solar:logout-2-bold-duotone') ?><span>Keluar</span>
        </button>
      </li>
    </ul>
    <div class="copyright">
      <hr class="separator my-3" style="--separator-color: var(--sidebar-submenu-border-color)" />
      <p class="text-xs text-muted-foreground">Masuk sebagai <strong><?= e($admin['nama']) ?></strong></p>
    </div>
  </footer>
</aside>