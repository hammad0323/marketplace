<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$items = active('portfolio', 'ORDER BY sort_order, id');
$cats = [];
foreach ($items as $p) {
    if ($p['category'] !== null && $p['category'] !== '') {
        $cats[slugify($p['category'])] = $p['category'];
    }
}

$page_title = 'Portfolio';
$page_desc  = 'Explore e-commerce stores, SaaS platforms, mobile apps, brands and campaigns built by ' . setting('site_name') . '.';
require __DIR__ . '/partials/header.php';

page_hero('Our *Portfolio*', 'A selection of projects we have designed, built and grown for our clients.', ['Portfolio' => ''], 'Work');
?>
<section class="section">
  <div class="container">
    <?php if (count($cats) > 1): ?>
      <div class="pf-filters pkg-tabs" data-reveal="up">
        <button type="button" class="pkg-tab active" data-filter="*"><i class="fa-solid fa-border-all"></i> All</button>
        <?php foreach ($cats as $slug => $name): ?>
          <button type="button" class="pkg-tab" data-filter="<?= e($slug) ?>"><i class="<?= e(portfolio_icon($name)) ?>"></i> <?= e($name) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="pf-grid">
      <?php foreach ($items as $i => $p) { portfolio_item($p, $i); } ?>
    </div>
    <?php if (!$items): ?><p class="center muted">Projects coming soon.</p><?php endif; ?>
  </div>
</section>
<?php
section_testimonials();
section_cta();
require __DIR__ . '/partials/footer.php';
