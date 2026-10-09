<?php if (current_admin()): ?>
  </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script>window.ADMIN = <?= json_encode(['base' => base_path(), 'csrf' => csrf_token()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sortable/Sortable.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
