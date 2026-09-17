<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$page = wh_fetch_one("SELECT * FROM pages WHERE business_id=? AND page_key='about'", 'i', [$businessId]);
$settings = wh_get_settings($businessId);
$halls = wh_get_halls($businessId, true);

$pageTitle = $page['title'] ?? 'About Us';
$seoPageKey = 'about';
$activeNav = 'about';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1><?= e($page['title'] ?? 'About Us') ?></h1>
    <p>Get to know the team behind your celebration.</p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:820px;">
    <div class="reveal" style="font-size:1.05rem;">
      <?= $page['content'] ?? '<p>Content coming soon.</p>' ?>
    </div>
  </div>
</section>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal">
      <div class="card" style="padding:26px;text-align:center;">
        <i class="fa-solid fa-building-columns" style="font-size:1.8rem;color:var(--primary);"></i>
        <h3 style="margin-top:14px;"><?= count($halls) ?> Halls</h3>
        <p>Across major cities in Pakistan.</p>
      </div>
      <div class="card" style="padding:26px;text-align:center;">
        <i class="fa-solid fa-heart" style="font-size:1.8rem;color:var(--primary);"></i>
        <h3 style="margin-top:14px;">Every Occasion</h3>
        <p>Weddings, walima, mehndi, corporate events &amp; more.</p>
      </div>
      <div class="card" style="padding:26px;text-align:center;">
        <i class="fa-solid fa-headset" style="font-size:1.8rem;color:var(--primary);"></i>
        <h3 style="margin-top:14px;">Dedicated Support</h3>
        <p>Our booking team is with you from enquiry to event day.</p>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
