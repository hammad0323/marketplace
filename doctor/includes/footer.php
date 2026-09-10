        </div>
    </main>
</div>
<script>window.APP = { loggedIn: true, role: <?= json_encode(current_role()) ?>, csrfToken: <?= json_encode(csrf_token()) ?>, currencySymbol: <?= json_encode(get_setting('currency_symbol', '$')) ?> };</script>
<script defer src="<?= asset_url('/assets/js/vendor/jquery.min.js') ?>"></script>
<script defer src="<?= asset_url('/assets/js/toast.js') ?>"></script>
<script defer src="<?= asset_url('/assets/js/main.js') ?>"></script>
<script defer src="<?= asset_url('/assets/js/notifications.js') ?>"></script>
<?php foreach (flash_get() as $f): ?>
<script>document.addEventListener('DOMContentLoaded', function(){ showToast('<?= e($f['type']) ?>', '<?= $f['type'] === 'success' ? 'Success' : ($f['type'] === 'error' ? 'Error' : 'Notice') ?>', '<?= e(addslashes($f['message'])) ?>'); });</script>
<?php endforeach; ?>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
