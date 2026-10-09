<?php if (!defined('EBAYA')) { http_response_code(403); exit; } ?>
<?php if (current_admin()): ?>
  </div>
</div>
<?php endif; ?>
<script>window.EBA = { base: <?= json_encode(BASE_PATH) ?>, csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<?= $adminScripts ?? '' ?>
</body>
</html>
