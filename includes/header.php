<?php
// Variabel yang diharapkan sudah di-set oleh halaman pemanggil:
// $page_title (string), $nav_active (string)
$page_title = $page_title ?? 'Dashboard';
$nav_active = $nav_active ?? 'dashboard';
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($page_title) ?> · Absensi PT Jafran Indonesia</title>
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
  <div class="app-shell" data-stisla-app-shell data-stisla-app-shell-auto-collapse="true">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="app-shell__main">
      <?php include __DIR__ . '/topbar.php'; ?>
      <div class="page content">
        <div class="content__container">
