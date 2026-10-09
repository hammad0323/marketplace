<?php
admin_header('Access denied');
?>
<div class="card"><div class="card-body text-center py-5">
  <i class="bi bi-shield-lock display-5 text-muted"></i>
  <h2 class="h4 mt-3">You do not have permission to view this page</h2>
  <p class="text-muted">Ask a Super Admin to grant the required permission to your role.</p>
  <a class="btn btn-primary" href="<?= e(admin_url()) ?>">Back to dashboard</a>
</div></div>
<?php admin_footer();
