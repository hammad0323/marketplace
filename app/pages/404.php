<?php
meta_set(['title' => 'Page not found', 'noindex' => true]);
$suggest = featured_products(4) ?: new_arrival_products(4, 'latest');
partial('header');
?>
<div class="container page-pad text-center notfound">
  <p class="notfound__code">404</p>
  <h1 class="page-title">This page has wandered off</h1>
  <p class="text-muted">The link may be outdated, or the piece is no longer available.</p>
  <form action="<?= e(path_url('search')) ?>" method="get" class="notfound__search"><input type="search" name="q" placeholder="Search the store" aria-label="Search"><button class="btn-lux">Search</button></form>
  <a class="link-arrow" href="<?= e(path_url('/')) ?>">Return home <i class="bi bi-arrow-right"></i></a>
  <?php if ($suggest): ?>
    <div class="product-grid product-grid--4 mt-5 text-start"><?php foreach ($suggest as $p) { partial('product-card', ['p' => $p]); } ?></div>
  <?php endif; ?>
</div>
<?php partial('footer');
