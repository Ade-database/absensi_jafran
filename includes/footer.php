        </div>
      </div>
    </main>
  </div>

  <div class="dialog dialog--sm" id="logoutConfirm" data-stisla-dialog data-state="closed" role="alertdialog" aria-modal="true" tabindex="-1">
    <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
    <div class="dialog__panel">
      <div class="dialog__content">
        <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><?= ic('solar:close-circle-linear') ?></button>
        <div class="dialog__header"><h3 class="dialog__title">Keluar dari aplikasi?</h3></div>
        <div class="dialog__body">
          <p class="text-muted-foreground">Apakah kamu yakin ingin keluar? Kamu perlu login lagi untuk mengakses dashboard.</p>
        </div>
        <div class="dialog__footer">
          <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
          <a href="logout.php" class="button button--danger">Ya, Keluar</a>
        </div>
      </div>
    </div>
  </div>

  <script type="module" src="https://cdn.jsdelivr.net/npm/@stisla/vanilla@3/dist/stisla.js"></script>
  <script src="assets/js/app-shell.js"></script>
  <script src="assets/js/theme.js"></script>
  <script src="assets/js/table-select.js"></script>
</body>
</html>