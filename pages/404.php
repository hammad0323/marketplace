<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
http_response_code(404);
page_set(['title' => 'Page not found', 'robots' => 'noindex,follow']);
require_once FS_ROOT . '/partials/product-card.php';
$popular = products_query(['sort' => 'popular', 'limit' => 5])['items'];
require FS_ROOT . '/partials/header.php';
?>
<div class="container text-center py-5">
  <div class="display-1 fw-bold text-brand">404</div>
  <h1 class="h3 fw-bold">Sorry, we couldn't find that page</h1>
  <p class="text-muted">The page may have moved. Try searching for what you need.</p>
  <form action="<?= url('search/') ?>" method="get" class="mx-auto my-4" style="max-width:480px">
    <div class="input-group"><input type="search" name="q" class="form-control form-control-lg" placeholder="Search products…" aria-label="Search"><button class="btn btn-brand btn-lg">Search</button></div>
  </form>
  <a href="<?= url('') ?>" class="btn btn-outline-brand me-2">Go to homepage</a><a href="<?= url('monthly-rashan/') ?>" class="btn btn-warning">Monthly Rashan</a>
</div>
<?php if ($popular): ?><div class="container"><div class="section-head"><h2>Popular right now</h2></div><?= render_product_grid($popular) ?></div><?php endif; ?>
<?php require FS_ROOT . '/partials/footer.php';
