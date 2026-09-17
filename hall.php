<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$slug = wh_input_get('slug');
$hall = wh_get_hall_by_slug($slug, $businessId);
if (!$hall || !$hall['is_public'] || $hall['status'] !== 'active') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$facilities = wh_hall_facilities($hall['id']);
$images = wh_hall_images($hall['id']);
$showAvailability = wh_setting_bool('show_public_availability', true, $businessId);

$pageTitle = $hall['name'];
$metaDescription = $hall['meta_description'] ?? '';
$activeNav = 'halls';
require __DIR__ . '/header.php';

$heroImg = !empty($hall['featured_image']) ? BASE_URL . '/' . $hall['featured_image'] : 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop';
?>
<section class="parallax-hero" style="min-height:52vh;">
  <div class="parallax-hero-bg" style="background-image:url('<?= e($heroImg) ?>');"></div>
  <div class="parallax-hero-overlay"></div>
  <div class="container">
    <span class="hero-badge"><i class="fa-solid fa-location-dot"></i> <?= e($hall['city']) ?><?= $hall['area'] ? ', ' . e($hall['area']) : '' ?></span>
    <h1 class="hero-title" style="font-size:2.6rem;"><?= e($hall['name']) ?></h1>
    <div class="hero-actions">
      <a href="<?= e(BASE_URL) ?>/booking?hall=<?= (int) $hall['id'] ?>" class="btn btn-primary">Book This Hall</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="two-col" style="display:grid;grid-template-columns:1.7fr 1fr;gap:40px;align-items:start;">
      <div class="reveal">
        <h2>About This Hall</h2>
        <p><?= nl2br(e($hall['description'] ?? '')) ?></p>

        <?php if ($facilities): ?>
        <h3 style="margin-top:36px;">Facilities</h3>
        <div class="badge-row">
          <?php foreach ($facilities as $f): ?><span class="chip"><i class="fa-solid <?= e($f['icon'] ?: 'fa-circle-check') ?>"></i> <?= e($f['facility_name']) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($images): ?>
        <h3 style="margin-top:36px;">Gallery</h3>
        <div class="gallery-grid">
          <?php foreach ($images as $img): ?>
          <div class="gallery-item" data-lightbox="<?= e(BASE_URL . '/' . $img['image_path']) ?>">
            <img src="<?= e(BASE_URL . '/' . $img['image_path']) ?>" alt="<?= e($img['alt_text'] ?: $hall['name']) ?>" loading="lazy">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($hall['map_embed'])): ?>
        <h3 style="margin-top:36px;">Location</h3>
        <div style="border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);">
          <?= $hall['map_embed'] /* admin-controlled trusted embed HTML */ ?>
        </div>
        <?php elseif (!empty($hall['map_url'])): ?>
        <h3 style="margin-top:36px;">Location</h3>
        <a href="<?= e($hall['map_url']) ?>" target="_blank" rel="noopener" class="btn btn-ghost"><i class="fa-solid fa-map"></i> Get Directions</a>
        <?php endif; ?>
      </div>

      <aside class="reveal">
        <div class="form-card">
          <h3>Package</h3>
          <p class="hall-price" style="font-size:1.4rem;">
            <?= $hall['price_type'] === 'per_person' ? wh_format_money($hall['per_person_price']) . ' <span style="font-size:.85rem;color:var(--muted);font-weight:400;">/ person</span>' : wh_format_money($hall['base_price']) ?>
          </p>
          <p style="font-size:.88rem;"><i class="fa-solid fa-users"></i> Capacity: <?= (int) $hall['capacity_min'] ?>–<?= (int) $hall['capacity_max'] ?> guests</p>
          <a href="<?= e(BASE_URL) ?>/booking?hall=<?= (int) $hall['id'] ?>" class="btn btn-primary btn-block" style="margin-top:14px;">Book This Hall</a>
        </div>

        <?php if ($showAvailability): ?>
        <div class="form-card" style="margin-top:22px;">
          <h3>Check Availability</h3>
          <div class="form-group">
            <label for="hallAvailDate">Select a date</label>
            <input type="date" id="hallAvailDate" min="<?= e(date('Y-m-d')) ?>">
          </div>
          <div id="hallAvailResult"></div>
        </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>

<?php if ($showAvailability): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
document.getElementById('hallAvailDate').addEventListener('change', function () {
  var box = document.getElementById('hallAvailResult');
  box.innerHTML = '<p class="hint">Checking…</p>';
  WH.fetchAvailability(this.value, <?= (int) $hall['id'] ?>).then(function (data) { WH.renderAvailabilityTable(box, data); });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
