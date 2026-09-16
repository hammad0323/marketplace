<?php
require_once __DIR__ . '/includes/functions.php';
http_response_code(404);
$pageTitle = 'Page Not Found | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section text-center">
  <h1 class="display-4 font-serif mb-3">404</h1>
  <p class="text-muted mb-4">The page you are looking for could not be found.</p>
  <a href="<?= url() ?>" class="btn-brand">Back to Home</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
