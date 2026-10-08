<?php
if (!defined('ROOT')) {
    require __DIR__ . '/includes/bootstrap.php';
    http_response_code(404);
}
$seo = ['title' => 'Page Not Found | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="section">
  <div class="container narrow center error-page" data-reveal>
    <span class="big-404">404</span>
    <h1 class="section-title">This page has slipped away</h1>
    <p class="muted">The page you are looking for may have been moved or no longer exists.</p>
    <div class="hero-btns center-btns"><a class="btn btn-dark" href="<?= url('') ?>">Back to Home</a><a class="btn btn-outline" href="<?= url('shop') ?>">Shop All</a></div>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
