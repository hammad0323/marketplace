<?php
require __DIR__ . '/config.php';

$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
$halls = wh_get_halls($businessId, true);
$featuredHalls = array_slice($halls, 0, 3);
$eventTypes = wh_get_event_types($businessId);
$faqs = array_slice(wh_fetch_all("SELECT * FROM faqs WHERE business_id=? AND status='active' ORDER BY sort_order", 'i', [$businessId]), 0, 4);
$banners = wh_get_banners($businessId, true);
$latestPosts = wh_fetch_all("SELECT * FROM blog_posts WHERE business_id=? AND status='published' ORDER BY published_at DESC LIMIT 3", 'i', [$businessId]);
$showAvailability = wh_setting_bool('show_public_availability', true, $businessId);
$cityCount = count(array_unique(array_filter(array_column($halls, 'city'))));
$maxCapacity = $halls ? max(array_column($halls, 'capacity_max')) : 0;

$pageTitle = 'Home';
$seoPageKey = 'home';
$activeNav = 'home';
require __DIR__ . '/header.php';

$fallbackHeroImages = [
    'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=1600&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?q=80&w=1600&auto=format&fit=crop',
];
if (!$banners) {
    // No admin-managed banners yet — fall back to one working slide so the homepage is never empty.
    $banners = [[
        'title' => $settings['tagline'] ?: 'Unforgettable Weddings Start Here',
        'subheading' => "Browse our halls, check real-time availability by date and time slot, and book online in minutes — no phone calls needed.",
        'background_image' => $featuredHalls[0]['featured_image'] ?? '',
        'cta_text' => 'Book a Hall',
        'cta_link' => '/booking',
    ]];
}
?>
<section class="hero-carousel">
  <?php foreach ($banners as $i => $banner): ?>
    <?php
    $bgImage = !empty($banner['background_image']) ? BASE_URL . '/' . $banner['background_image'] : $fallbackHeroImages[$i % count($fallbackHeroImages)];
    $ctaText = $banner['cta_text'] ?: 'Book a Hall';
    $ctaLink = $banner['cta_link'] ?: (BASE_URL . '/booking');
    if (!preg_match('#^https?://#i', $ctaLink)) {
        $ctaLink = BASE_URL . '/' . ltrim($ctaLink, '/');
    }
    ?>
  <div class="hero-slide<?= $i === 0 ? ' active' : '' ?>">
    <div class="hero-slide-bg" style="background-image:url('<?= e($bgImage) ?>');"></div>
    <div class="hero-slide-overlay"></div>
    <div class="container">
      <span class="hero-badge"><i class="fa-solid fa-star"></i> Pakistan's Trusted Wedding &amp; Event Venues</span>
      <h1 class="hero-title"><?= e($banner['title']) ?></h1>
      <?php if (!empty($banner['subheading'])): ?><p class="hero-sub"><?= e($banner['subheading']) ?></p><?php endif; ?>
      <div class="hero-actions">
        <a href="<?= e($ctaLink) ?>" class="btn btn-primary"><?= e($ctaText) ?> <i class="fa-solid fa-arrow-right"></i></a>
        <a href="<?= e(BASE_URL) ?>/availability" class="btn btn-outline">Check Availability</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (count($banners) > 1): ?>
    <button type="button" class="hero-arrow prev" aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>
    <button type="button" class="hero-arrow next" aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="hero-dots"></div>
  <?php endif; ?>
</section>

<section class="section" style="padding:48px 0;">
  <div class="container">
    <div class="stats-bar reveal">
      <div class="stat-box"><strong><?= count($halls) ?>+</strong><span>Halls</span></div>
      <div class="stat-box"><strong><?= max(1, $cityCount) ?></strong><span>Cities</span></div>
      <div class="stat-box"><strong><?= $maxCapacity ?>+</strong><span>Max Guest Capacity</span></div>
      <div class="stat-box"><strong>100%</strong><span>Online Booking</span></div>
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
    <?php if (count($halls) > 3): ?>
    <div style="text-align:center;margin-top:36px;">
      <a href="<?= e(BASE_URL) ?>/halls" class="btn btn-ghost">View All Halls <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-tint">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Why Choose Us</span>
      <h2>Built for a Stress-Free Booking Experience</h2>
    </div>
    <div class="grid grid-4 reveal">
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <h3>Real-Time Availability</h3>
        <p>See exactly which hall, date and time slot is open — updated the instant a booking is made.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-lock"></i></div>
        <h3>Secure Online Booking</h3>
        <p>Submit your request in minutes with conflict-proof scheduling — no double-bookings, ever.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-tags"></i></div>
        <h3>Transparent Pricing</h3>
        <p>Clear package pricing and advance payment terms shown upfront — no hidden surprises.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-headset"></i></div>
        <h3>Dedicated Support</h3>
        <p>Our booking team is with you from your first enquiry all the way to event day.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($showAvailability): ?>
<section class="section">
  <div class="container" style="max-width:900px;">
    <div class="section-head reveal">
      <span class="eyebrow">Live Calendar</span>
      <h2>Check Availability at a Glance</h2>
      <p>Green means open, amber means partially booked, red means fully booked — click any date for hall-by-hall detail.</p>
    </div>
    <div class="wh-calendar-card reveal">
      <div class="cal-legend">
        <span><span class="dot" style="background:var(--success);"></span> Available</span>
        <span><span class="dot" style="background:var(--warning);"></span> Partially Booked</span>
        <span><span class="dot" style="background:var(--danger);"></span> Fully Booked</span>
      </div>
      <div id="homeCalendar"></div>
    </div>
    <div style="text-align:center;margin-top:28px;">
      <a href="<?= e(BASE_URL) ?>/availability" class="btn btn-ghost">Open Full Availability Checker <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>
<?php endif; ?>

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

<section class="section" style="padding-bottom:0;">
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

<?php if ($latestPosts): ?>
<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">From the Blog</span>
      <h2>Wedding Planning Tips &amp; Guides</h2>
    </div>
    <div class="grid grid-3">
      <?php foreach ($latestPosts as $post): $pimg = !empty($post['featured_image']) ? BASE_URL . '/' . $post['featured_image'] : 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=800&auto=format&fit=crop'; ?>
      <a href="<?= e(BASE_URL) ?>/blog/<?= e($post['slug']) ?>" class="card reveal" style="text-decoration:none;color:inherit;">
        <div class="hall-card-img" style="background-image:url('<?= e($pimg) ?>');"></div>
        <div class="hall-card-body">
          <?php if ($post['category']): ?><span class="eyebrow"><?= e($post['category']) ?></span><?php endif; ?>
          <h3><?= e($post['title']) ?></h3>
          <p style="font-size:.88rem;"><?= e($post['excerpt']) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section section-tint">
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

<section class="section">
  <div class="container">
    <div class="card reveal" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;padding:56px;text-align:center;border:none;">
      <h2 style="color:#fff;">Ready to Book Your Event?</h2>
      <p style="color:rgba(255,255,255,.85);">Check availability and submit your booking request online — it only takes a few minutes.</p>
      <a href="<?= e(BASE_URL) ?>/booking" class="btn btn-outline">Start Booking <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<script src="<?= e(BASE_URL) ?>/assets/js/hero-carousel.js"></script>
<script>WH.initHeroCarousel(document.querySelector('.hero-carousel'));</script>
<?php if ($showAvailability): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/calendar-widget.js"></script>
<script>
WH.initCalendar(document.getElementById('homeCalendar'), {
  onDateClick: function (dateStr) {
    window.location.href = '<?= e(BASE_URL) ?>/availability?date=' + dateStr;
  }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
