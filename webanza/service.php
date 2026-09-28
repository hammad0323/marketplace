<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$service = row('SELECT * FROM services WHERE slug = ? AND is_active = 1', [$_GET['slug'] ?? '']);
if (!$service) {
    require __DIR__ . '/404.php';
    exit;
}
$all = active('services', 'ORDER BY sort_order, id');

// Package categories linked to this service in the admin panel.
$related = active('package_categories', 'AND service_id = ? ORDER BY sort_order, id', [$service['id']]);

$page_title = $service['title'];
$page_desc  = $service['short_desc'];
$page_image = $service['image'];
require __DIR__ . '/partials/header.php';

page_hero($service['title'], (string) $service['short_desc'], ['Services' => 'services.php', $service['title'] => ''], 'Service');
?>
<section class="section">
  <div class="container detail-grid">
    <div>
      <div class="detail-cover" data-reveal="zoom"><?= thumb($service['image'], $service['title'], $service['title'], $service['icon']) ?></div>
      <div class="prose" data-reveal="up"><?= rich($service['description']) ?></div>
      <?php if ($features = lines($service['features'])): ?>
        <h3 data-reveal="up" style="margin-top:40px">What's included</h3>
        <div class="feature-grid">
          <?php foreach ($features as $i => $f): ?>
            <div data-reveal="up" style="--d:<?= ($i % 2) * .1 ?>s"><i class="fa-solid fa-circle-check"></i><?= e($f) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <a href="<?= e(url('contact.php?service=' . urlencode($service['title']))) ?>" class="btn magnetic" data-reveal="up">Start This Project <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <aside>
      <div class="sidebar-box" data-reveal="right">
        <h4>All Services</h4>
        <ul class="side-links">
          <?php foreach ($all as $s): ?>
            <li><a class="<?= $s['id'] === $service['id'] ? 'active' : '' ?>" href="<?= e(url('service.php?slug=' . $s['slug'])) ?>"><?= e($s['title']) ?> <i class="fa-solid fa-arrow-right"></i></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="sidebar-box dark-box" data-reveal="right" style="--d:.1s">
        <h4>Need help choosing?</h4>
        <p>Get a free consultation and a detailed quote within 24 hours.</p>
        <a href="<?= e(url('contact.php')) ?>" class="btn btn-block">Get a Free Quote</a>
        <?php if (setting('phone')): ?><p style="margin:18px 0 0"><i class="fa-solid fa-phone"></i> <a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p><?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<?php foreach ($related as $c):
    $pk = active('packages', 'AND category_id = ? ORDER BY sort_order, id', [$c['id']]);
    if (!$pk) { continue; } ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow" data-reveal="up"><?= e($c['name']) ?> Packages</span>
      <?= split_heading('Choose your *' . $c['name'] . '* plan') ?>
      <?php if ($c['description']): ?><p data-reveal="up"><?= e($c['description']) ?></p><?php endif; ?>
    </div>
    <div class="pkg-grid"><?php foreach ($pk as $i => $p) { package_card($p, $i); } ?></div>
  </div>
</section>
<?php endforeach; ?>

<?php
section_process();
section_cta();
require __DIR__ . '/partials/footer.php';
