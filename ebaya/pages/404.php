<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
http_response_code(404);
seo_set(['title' => 'Page not found', 'noindex' => true]);
[$suggest] = products_query(['sort' => 'featured', 'limit' => 4]);
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section">
  <div class="container-eb text-center not-found">
    <span class="eyebrow">Error 404</span>
    <h1 class="page-title">This page has wandered off</h1>
    <p class="text-muted">The page you're looking for may have moved. Try searching, or explore our collection.</p>
    <form action="<?= e(url('search')) ?>" class="nf-search"><input class="form-control" name="q" placeholder="Search abayas…" aria-label="Search"><button class="btn btn-eb btn-primary-eb">Search</button></form>
    <a href="<?= e(url('shop')) ?>" class="btn btn-eb btn-outline-eb mt-3">Shop all abayas</a>
  </div>
  <?php if ($suggest): ?><div class="container-eb mt-5"><h2 class="section-title small text-center">You might love</h2><div class="product-grid grid-4"><?php foreach ($suggest as $p): ?><div><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?></div></div><?php endif; ?>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
