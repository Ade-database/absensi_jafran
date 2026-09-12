<?php
$f = ambil_flash();
if ($f):
    $warna = $f['type'] === 'error' ? 'danger' : ($f['type'] === 'warning' ? 'warning' : 'success');
    $iconName = $f['type'] === 'error' ? 'solar:close-circle-bold' : ($f['type'] === 'warning' ? 'solar:danger-triangle-bold-duotone' : 'solar:check-circle-bold');
?>
<div class="alert alert--<?= $warna ?>">
  <?= ic($iconName) ?>
  <span><?= e($f['pesan']) ?></span>
</div>
<?php endif; ?>
