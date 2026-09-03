<?php
if (!function_exists('base_url')) {
    require __DIR__ . '/config/config.php';
}
http_response_code(404);
$pageTitle = 'Page Not Found';
require __DIR__ . '/includes/header.php';
?>
<div class="container error-page">
  <h1>404</h1>
  <p>The page you're looking for doesn't exist or may have been moved.</p>
  <a class="btn btn-primary" href="<?= base_url() ?>">Back to Home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
