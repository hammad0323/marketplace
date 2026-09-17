<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$halls = wh_get_halls($businessId, true);
$cities = array_values(array_unique(array_filter(array_column($halls, 'city'))));
$cityFilter = wh_input_get('city');
if ($cityFilter) {
    $halls = array_values(array_filter($halls, fn($h) => $h['city'] === $cityFilter));
}

$pageTitle = 'Our Halls';
$seoPageKey = 'halls';
$activeNav = 'halls';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Our Wedding Halls</h1>
    <p>Explore every venue and find the perfect fit for your celebration.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php if ($cities): ?>
    <div class="badge-row reveal" style="margin-bottom:32px;">
      <a href="<?= e(BASE_URL) ?>/halls" class="chip" style="<?= !$cityFilter ? 'background:var(--primary);color:#fff;' : '' ?>">All Cities</a>
      <?php foreach ($cities as $city): ?>
        <a href="<?= e(BASE_URL) ?>/halls?city=<?= urlencode($city) ?>" class="chip" style="<?= $cityFilter === $city ? 'background:var(--primary);color:#fff;' : '' ?>"><?= e($city) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="grid grid-3">
      <?php foreach ($halls as $hall): $img = !empty($hall['featured_image']) ? BASE_URL . '/' . $hall['featured_image'] : 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=800&auto=format&fit=crop'; ?>
      <a href="<?= e(BASE_URL) ?>/hall/<?= e($hall['slug']) ?>" class="card reveal" style="text-decoration:none;color:inherit;">
        <div class="hall-card-img" style="background-image:url('<?= e($img) ?>');">
          <span class="hall-card-badge"><?= e($hall['city']) ?></span>
        </div>
        <div class="hall-card-body">
          <h3><?= e($hall['name']) ?></h3>
          <p style="font-size:.88rem;"><?= e(mb_strimwidth(strip_tags($hall['description'] ?? ''), 0, 110, '…')) ?></p>
          <div class="hall-meta">
            <span><i class="fa-solid fa-users"></i> <?= (int) $hall['capacity_min'] ?>–<?= (int) $hall['capacity_max'] ?> guests</span>
          </div>
          <div class="hall-price"><?= $hall['price_type'] === 'per_person' ? wh_format_money($hall['per_person_price']) . ' / person' : wh_format_money($hall['base_price']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$halls): ?><p>No halls found.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
