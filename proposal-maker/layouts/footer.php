<?php if (empty($noLayout)): ?>
    </div>
  </main>
</div>
<?php endif; ?>
<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
