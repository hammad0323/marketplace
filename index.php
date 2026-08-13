<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = '';
$metaDescription = get_setting($conn, 'site_tagline', 'Plan, book, and explore — all in one place.');

$homeSeo = db_select_one($conn, 'SELECT * FROM seo_settings WHERE page_type = "home" AND page_reference_id IS NULL');
if ($homeSeo) {
    if (!empty($homeSeo['meta_title'])) {
        $pageTitle = $homeSeo['meta_title'];
    }
    if (!empty($homeSeo['meta_description'])) {
        $metaDescription = $homeSeo['meta_description'];
    }
    if (!empty($homeSeo['og_image'])) {
        $ogImage = $homeSeo['og_image'];
    }
    if (!empty($homeSeo['canonical_url'])) {
        $canonicalUrl = $homeSeo['canonical_url'];
    }
}

$featuredCities = db_select($conn, 'SELECT * FROM cities WHERE is_active = 1 ORDER BY is_featured DESC, sort_order ASC LIMIT 8');
$topCategories = db_select($conn, 'SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order ASC LIMIT 12');

$totalProviders = db_count($conn, 'SELECT COUNT(*) FROM providers WHERE status = "approved"');
$totalServices = db_count($conn, 'SELECT COUNT(*) FROM services WHERE status = "approved"');
$totalCities = db_count($conn, 'SELECT COUNT(*) FROM cities WHERE is_active = 1');
$totalBookings = db_count($conn, 'SELECT COUNT(*) FROM bookings WHERE status IN ("confirmed","completed")');

$featuredProviders = db_select(
    $conn,
    'SELECT p.*, c.name AS city_name FROM providers p LEFT JOIN cities c ON c.id = p.city_id
     WHERE p.status = "approved" ORDER BY p.is_featured DESC, p.avg_rating DESC LIMIT 6'
);

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="hero-blob hero-blob-3"></div>

  <div class="container-xl">
    <div class="hero-content reveal in-view">
      <span class="hero-eyebrow"><i class="bi bi-stars"></i> Trip planning, reinvented</span>
      <h1 class="hero-title">Plan the trip. <span class="accent">Book everything.</span> One marketplace.</h1>
      <p class="hero-sub">Hotels, cars, tours, restaurants and experiences from verified local providers — plus a trip planner that builds your itinerary and budget for you.</p>
    </div>

    <form class="search-card reveal in-view" action="<?php echo url('/pages/search.php'); ?>" method="get">
      <div class="search-field">
        <label>Where to</label>
        <input type="text" name="destination" placeholder="City, region or landmark" autocomplete="off" data-autocomplete>
      </div>
      <div class="search-field">
        <label>Category</label>
        <select name="category">
          <option value="">Anything</option>
          <?php foreach ($topCategories as $cat): ?>
            <option value="<?php echo e($cat['slug']); ?>"><?php echo e($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="search-field">
        <label>When</label>
        <input type="date" name="date">
      </div>
      <div class="search-field">
        <label>Guests</label>
        <input type="number" name="guests" min="1" value="2">
      </div>
      <button type="submit" class="btn-w btn-primary search-submit"><i class="bi bi-search"></i> Search</button>
    </form>
    <div style="text-align:center;margin-top:16px;position:relative;z-index:2;">
      <button type="button" class="btn-w btn-ghost btn-sm" style="color:rgba(255,255,255,0.85);" data-geo-trigger>
        <i class="bi bi-crosshair"></i> Use my location
      </button>
    </div>

    <div class="hero-stats">
      <div class="hero-stat"><div class="num" data-count="<?php echo (int) $totalProviders; ?>">0</div><div class="label">Verified Providers</div></div>
      <div class="hero-stat"><div class="num" data-count="<?php echo (int) $totalServices; ?>">0</div><div class="label">Listed Services</div></div>
      <div class="hero-stat"><div class="num" data-count="<?php echo (int) $totalCities; ?>">0</div><div class="label">Cities</div></div>
      <div class="hero-stat"><div class="num" data-count="<?php echo (int) $totalBookings; ?>">0</div><div class="label">Trips Booked</div></div>
    </div>
  </div>
</section>

<section class="section" id="cities">
  <div class="container-xl">
    <div class="section-head reveal">
      <span class="eyebrow"><i class="bi bi-geo-alt"></i> Destinations</span>
      <h2 class="section-heading">Explore cities</h2>
      <p class="section-sub">Pick a destination and see everything available there — stays, transport, food and things to do.</p>
    </div>
    <div class="city-grid stagger reveal">
      <?php foreach ($featuredCities as $city): ?>
        <a class="city-card" href="<?php echo url('/pages/city.php'); ?>?slug=<?php echo e($city['slug']); ?>">
          <?php if (!empty($city['image'])): ?>
            <img src="<?php echo e($city['image']); ?>" alt="<?php echo e($city['name']); ?>" loading="lazy">
          <?php endif; ?>
          <div class="city-card-body">
            <div class="name"><?php echo e($city['name']); ?></div>
            <div class="count"><i class="bi bi-arrow-right"></i> Explore</div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$featuredCities): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="icon-wrap"><i class="bi bi-globe"></i></div>
          <h4>No cities yet</h4>
          <p>Admin can add destinations from the CMS in Phase 3.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section-tight" style="background:var(--white);" id="categories">
  <div class="container-xl">
    <div class="section-head center reveal">
      <span class="eyebrow"><i class="bi bi-grid"></i> Categories</span>
      <h2 class="section-heading">Everything for your trip, in one place</h2>
      <p class="section-sub">Unlimited categories, fully managed by the admin — hotels today, anything tomorrow.</p>
    </div>
    <div class="category-grid stagger reveal">
      <?php foreach ($topCategories as $cat): ?>
        <a class="category-card" href="<?php echo url('/pages/category.php'); ?>?slug=<?php echo e($cat['slug']); ?>">
          <div class="icon-wrap"><i class="bi <?php echo e($cat['icon'] ?: 'bi-tag'); ?>"></i></div>
          <div class="name"><?php echo e($cat['name']); ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <div class="section-head center reveal">
      <span class="eyebrow"><i class="bi bi-award"></i> Featured</span>
      <h2 class="section-heading">Featured providers</h2>
      <p class="section-sub">Verified businesses our travelers love.</p>
    </div>

    <?php if ($featuredProviders): ?>
      <div class="provider-grid stagger reveal">
        <?php foreach ($featuredProviders as $p): ?>
          <a class="provider-card" href="<?php echo url('/pages/provider.php'); ?>?slug=<?php echo e($p['slug']); ?>">
            <div class="thumb">
              <?php if ($p['is_verified']): ?><span class="badge-pill"><i class="bi bi-patch-check-fill"></i> Verified</span><?php endif; ?>
              <?php if (!empty($p['cover_image'])): ?><img src="<?php echo e($p['cover_image']); ?>" alt="<?php echo e($p['business_name']); ?>"><?php endif; ?>
            </div>
            <div class="card-body">
              <div class="card-title"><?php echo e($p['business_name']); ?></div>
              <div class="card-meta"><i class="bi bi-geo-alt"></i> <?php echo e($p['city_name'] ?? 'Multiple locations'); ?></div>
              <div class="card-footer-row">
                <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $p['avg_rating'], 1); ?> <span style="color:var(--ink-mute);font-weight:500;">(<?php echo (int) $p['review_count']; ?>)</span></div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-shop"></i></div>
        <h4>No approved providers yet</h4>
        <p>Once businesses register and admin approves them, they'll be featured here.</p>
        <a href="<?php echo url('/provider/register.php'); ?>" class="btn-w btn-primary">Register your business</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section-tight" style="background:var(--white);">
  <div class="container-xl">
    <div class="section-head center reveal">
      <span class="eyebrow"><i class="bi bi-lightning"></i> Simple by design</span>
      <h2 class="section-heading">How <?php echo e($siteName); ?> works</h2>
    </div>
    <div class="steps-grid stagger reveal">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-title">Discover</div>
        <div class="step-text">Search and browse hotels, transport, food and experiences by city or category — no account needed.</div>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-title">Plan</div>
        <div class="step-text">Build a day-by-day itinerary in the Trip Planner and watch your estimated budget update live.</div>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-title">Book</div>
        <div class="step-text">Reserve services directly with verified providers, with clear pricing and cancellation policies.</div>
      </div>
      <div class="step-card">
        <div class="step-num">4</div>
        <div class="step-title">Go</div>
        <div class="step-text">Message providers, track bookings, and leave reviews — all from your dashboard.</div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <div class="cta-band reveal-scale">
      <div>
        <h3>Are you a hotel, driver, guide or restaurant?</h3>
        <p>List your services and reach travelers actively planning their trip.</p>
      </div>
      <a href="<?php echo url('/provider/register.php'); ?>" class="btn-w btn-white">Become a provider <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</section>

<?php $extraJs = '<script src="' . ASSETS_URL . '/js/search.js"></script>'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
