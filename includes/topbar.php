<?php $admin = admin_sekarang(); ?>
<header class="navbar">
  <button type="button" class="button button--ghost button--neutral button--icon-only button--flush-start"
          data-stisla-app-shell-toggle="auto" aria-label="Buka/tutup sidebar">
    <?= ic('solar:hamburger-menu-linear') ?>
  </button>

  <div class="ms-auto">
    <div class="flex gap-1">
      <button type="button" class="button button--ghost button--neutral button--icon-only" data-theme-toggle aria-label="Ganti tema">
        <?= ic('solar:moon-linear') ?>
      </button>

      <div class="menu">
        <button type="button" class="button button--ghost button--neutral flex items-center gap-2"
                data-stisla-menu-trigger="topbarUser" aria-haspopup="menu" aria-expanded="false" aria-controls="topbarUser">
          <span class="hidden sm:inline font-medium"><?= e($admin['nama']) ?></span>
          <span class="avatar avatar--sm avatar--circle" data-stisla-avatar>
            <span class="avatar__fallback"><?= e(strtoupper(substr($admin['nama'], 0, 2))) ?></span>
          </span>
          <?= ic('solar:alt-arrow-down-linear') ?>
        </button>
        <div class="menu__popup w-48" id="topbarUser" data-stisla-menu role="menu" data-state="closed">
          <div class="menu__group" role="group" aria-labelledby="topbarUserHead">
            <h3 class="menu__group-label" id="topbarUserHead"><?= e($admin['username']) ?></h3>
            <a href="pengaturan.php" class="menu__item" role="menuitem"><?= ic('solar:settings-bold-duotone') ?>Pengaturan</a>
          </div>
          <hr class="menu__separator" role="separator" />
          <a href="logout.php" class="menu__item" role="menuitem"><?= ic('solar:logout-2-bold-duotone') ?>Keluar</a>
        </div>
      </div>
    </div>
  </div>
</header>
