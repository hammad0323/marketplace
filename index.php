<?php
require __DIR__ . '/config.php';

$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
$halls = wh_get_halls($businessId, true);
$featuredHalls = array_slice($halls, 0, 3);
$eventTypes = wh_get_event_types($businessId);
$faqs = array_slice(wh_fetch_all("SELECT * FROM faqs WHERE business_id=? AND status='active' ORDER BY sort_order", 'i', [$businessId]), 0, 4);

$pageTitle = 'Home';
$seoPageKey = 'home';
$activeNav = 'home';
require __DIR__ . '/header.php';

$heroImage = !empty($featuredHalls[0]['featured_image']) ? BASE_URL . '/' . $featuredHalls[0]['featured_image'] : 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop';
?>
<section class="parallax-hero">
  <div class="parallax-hero-bg" style="background-image:url('<?= e($heroImage) ?>');"></div>
  <div class="parallax-hero-overlay"></div>
  <div class="container">
    <span class="hero-badge"><i class="fa-solid fa-star"></i> Pakistan's Trusted Wedding &amp; Event Venues</span>
    <h1 class="hero-title"><?= e($settings['tagline'] ?? 'Unforgettable Weddings Start Here') ?></h1>
    <p class="hero-sub">Browse our halls, check real-time availability by date and time slot, and book online in minutes — no phone calls needed.</p>
    <div class="hero-actions">
      <a href="<?= e(BASE_URL) ?>/booking" class="btn btn-primary">Book a Hall <i class="fa-solid fa-arrow-right"></i></a>
      <a href="<?= e(BASE_URL) ?>/availability" class="btn btn-outline">Check Availability</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><strong><?= count($halls) ?>+</strong><span>Halls</span></div>
      <div class="hero-stat"><strong>800+</strong><span>Guest Capacity</span></div>
      <div class="hero-stat"><strong>3</strong><span>Cities</span></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Our Venues</span>
      <h2>Featured Halls</h2>
      <p>Elegant, spacious and fully equipped venues for every kind of celebration.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ($featuredHalls as $hall): $img = !empty($hall['featured_image']) ? BASE_URL . '/' . $hall['featured_image'] : 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=800&auto=format&fit=crop'; ?>
      <a href="<?= e(BASE_URL) ?>/hall/<?= e($hall['slug']) ?>" class="card reveal" style="text-decoration:none;color:inherit;">
        <div class="hall-card-img" style="background-image:url('<?= e($img) ?>');">
          <span class="hall-card-badge"><?= e($hall['city']) ?></span>
        </div>
        <div class="hall-card-body">
          <h3><?= e($hall['name']) ?></h3>
          <div class="hall-meta">
            <span><i class="fa-solid fa-users"></i> <?= (int) $hall['capacity_min'] ?>–<?= (int) $hall['capacity_max'] ?> guests</span>
          </div>
          <div class="hall-price"><?= $hall['price_type'] === 'per_person' ? wh_format_money($hall['per_person_price']) . ' / person' : wh_format_money($hall['base_price']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$featuredHalls): ?><p>Halls will appear here once added by the admin.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="section section-dark">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">How It Works</span>
      <h2>Book Your Date in 4 Simple Steps</h2>
    </div>
    <div class="steps reveal">
      <div class="step"><h3>Choose a Hall</h3><p>Browse our venues by city, capacity and price.</p></div>
      <div class="step"><h3>Pick Date &amp; Slot</h3><p>See live availability for Morning, Evening or Night.</p></div>
      <div class="step"><h3>Submit Request</h3><p>Fill your event details — we check availability instantly.</p></div>
      <div class="step"><h3>Get Confirmed</h3><p>Our team confirms your booking and advance payment.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Events We Host</span>
      <h2>For Every Celebration</h2>
    </div>
    <div class="badge-row reveal" style="justify-content:center;">
      <?php foreach ($eventTypes as $et): ?><span class="chip"><i class="fa-solid fa-champagne-glasses"></i> <?= e($et['name']) ?></span><?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($faqs): ?>
<section class="section">
  <div class="container" style="max-width:760px;">
    <div class="section-head reveal">
      <span class="eyebrow">Questions</span>
      <h2>Frequently Asked</h2>
    </div>
    <div class="reveal">
      <?php foreach ($faqs as $faq): ?>
        <details class="faq-item"><summary><?= e($faq['question']) ?> <i class="fa-solid fa-chevron-down"></i></summary><p><?= e($faq['answer']) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="card reveal" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;padding:56px;text-align:center;border:none;">
      <h2 style="color:#fff;">Ready to Book Your Event?</h2>
      <p style="color:rgba(255,255,255,.85);">Check availability and submit your booking request online — it only takes a few minutes.</p>
      <a href="<?= e(BASE_URL) ?>/booking" class="btn btn-outline">Start Booking <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
