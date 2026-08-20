<?php
require __DIR__ . '/includes/config.php';

$categories = get_categories(true);
$popular = get_popular_tools(8);
$featured = get_featured_tools(8);
$trending = get_trending_tools(6);
$recent = get_recent_tools(8);

$totalTools = (int) (tp_query_one("SELECT COUNT(*) c FROM tools WHERE status='published'")['c'] ?? 0);
$totalCategories = count($categories);

$pageTitle = tp_setting('site_name') . ' — ' . tp_setting('site_tagline');
$pageDescriptionFallback = 'Free calculators, converters, generators and productivity tools for professionals, students and everyday users.';
$bodyClass = 'home-page';
require __DIR__ . '/includes/header.php';
?>

<section class="parallax-hero py-5">
  <div class="parallax-hero-bg"></div>
  <div class="hero-blob" style="width:220px;height:220px;background:var(--tp-cyan);top:10%;left:8%;"></div>
  <div class="hero-blob" style="width:160px;height:160px;background:var(--tp-violet);top:60%;right:10%;animation-delay:2s;"></div>
  <div class="tp-container py-5 text-center">
    <span class="tp-eyebrow text-white-50">One Platform. Hundreds of Smart Tools.</span>
    <h1 class="hero-title mt-2 mx-auto">Smart Tools for Work, Business &amp; Everyday Life</h1>
    <p class="hero-sub mx-auto mt-3">Free calculators, converters, generators and productivity tools designed for professionals, students and everyday users.</p>

    <form action="<?= tp_url('search') ?>" method="get" class="tp-search-shell mx-auto mt-4" style="max-width:640px;">
      <i class="bi bi-search text-muted"></i>
      <input type="text" name="q" placeholder="Search <?= (int) $totalTools ?>+ tools..." aria-label="Search tools">
      <button type="submit">Search</button>
    </form>

    <div class="d-flex justify-content-center gap-3 mt-4">
      <a href="<?= tp_url('all-tools') ?>" class="btn btn-light fw-semibold px-4">Explore All Tools</a>
      <a href="#browse-profession" class="btn btn-outline-light fw-semibold px-4">Browse Categories</a>
    </div>

    <div class="row mt-5 text-white-50">
      <div class="col-4"><div class="fs-2 fw-bold text-white" data-counter="<?= $totalTools ?>">0</div>Tools</div>
      <div class="col-4"><div class="fs-2 fw-bold text-white" data-counter="<?= $totalCategories ?>">0</div>Categories</div>
      <div class="col-4"><div class="fs-2 fw-bold text-white" data-counter="100">0</div>% Free</div>
    </div>
  </div>
</section>

<section class="tp-section">
  <div class="tp-container">
    <div class="d-flex justify-content-between align-items-end mb-4 reveal">
      <div><span class="tp-eyebrow">Most used</span><h2 class="tp-section-title">Popular Tools</h2></div>
      <a href="<?= tp_url('popular') ?>">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
      <?php foreach ($popular as $t): ?>
      <div class="col-sm-6 col-lg-3 reveal">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$popular): ?><p class="text-muted">Mark tools as "Popular" from the admin panel to feature them here.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="tp-section tp-section-dark" id="browse-profession">
  <div class="tp-container">
    <div class="text-center mb-5 reveal">
      <span class="tp-eyebrow">By profession</span>
      <h2 class="tp-section-title">Browse Tools by Category</h2>
    </div>
    <div class="row g-3">
      <?php foreach ($categories as $cat): ?>
      <div class="col-6 col-md-4 col-lg-3 reveal">
        <a href="<?= tp_url($cat['slug']) ?>" class="tp-card text-decoration-none d-block p-3 h-100" style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08);">
          <span class="tp-icon mb-2" style="background:<?= e($cat['color']) ?>;"><i class="bi <?= e($cat['icon']) ?>"></i></span>
          <h3 class="h6 fw-bold text-white mb-1"><?= e($cat['name']) ?></h3>
          <p class="text-white-50 small mb-0"><?= (int) get_tool_count_for_category((int) $cat['id']) ?> tools</p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($trending): ?>
<section class="tp-section">
  <div class="tp-container">
    <div class="d-flex justify-content-between align-items-end mb-4 reveal">
      <div><span class="tp-eyebrow">Right now</span><h2 class="tp-section-title">Trending Tools</h2></div>
      <a href="<?= tp_url('trending') ?>">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
      <?php foreach ($trending as $t): ?>
      <div class="col-sm-6 col-lg-4 reveal">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="tp-section" style="background:var(--tp-surface-2);">
  <div class="tp-container">
    <div class="d-flex justify-content-between align-items-end mb-4 reveal">
      <div><span class="tp-eyebrow">Editor's pick</span><h2 class="tp-section-title">Featured Tools</h2></div>
    </div>
    <div class="row g-3">
      <?php foreach ($featured as $t): ?>
      <div class="col-sm-6 col-lg-3 reveal">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="tp-section">
  <div class="tp-container">
    <div class="d-flex justify-content-between align-items-end mb-4 reveal">
      <div><span class="tp-eyebrow">Fresh</span><h2 class="tp-section-title">Recently Added</h2></div>
    </div>
    <div class="row g-3">
      <?php foreach ($recent as $t): ?>
      <div class="col-sm-6 col-lg-3 reveal">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="tp-section" style="background:var(--tp-surface-2);">
  <div class="tp-container">
    <div class="text-center mb-5 reveal">
      <span class="tp-eyebrow">Why ToolStack</span>
      <h2 class="tp-section-title">Built for speed, accuracy and privacy</h2>
    </div>
    <div class="row g-4 text-center">
      <?php foreach ([
          ['bi-lightning-charge', 'Fast', 'Instant client-side calculations — no waiting on a server round-trip.'],
          ['bi-gift', 'Free', 'Every tool is free to use, with no paywalls or hidden limits.'],
          ['bi-check2-circle', 'Accurate', 'Real, documented formulas — reviewed, not guessed.'],
          ['bi-incognito', 'No Registration', 'Use any tool instantly. No account required.'],
          ['bi-phone', 'Mobile Friendly', 'Every calculator is optimized for touch and small screens.'],
          ['bi-shield-lock', 'Privacy Focused', 'Sensitive inputs never leave your browser unless you explicitly opt in.'],
      ] as $item): ?>
      <div class="col-6 col-md-4 col-lg-2 reveal">
        <div class="tp-icon mx-auto mb-2" style="background:var(--tp-gradient-accent);"><i class="bi <?= $item[0] ?>"></i></div>
        <h3 class="h6 fw-bold"><?= $item[1] ?></h3>
        <p class="text-muted small"><?= $item[2] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="tp-section tp-section-dark text-center">
  <div class="tp-container reveal">
    <h2 class="tp-section-title">Find the right tool for your next task.</h2>
    <a href="<?= tp_url('all-tools') ?>" class="btn btn-light fw-semibold px-4 mt-3">Explore All Tools</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
