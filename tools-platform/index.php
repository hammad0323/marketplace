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

<section class="parallax-hero pt-5 pb-3">
  <div class="parallax-hero-bg"></div>
  <div class="hero-blob" style="width:220px;height:220px;background:var(--tp-cyan);top:10%;left:8%;"></div>
  <div class="hero-blob" style="width:160px;height:160px;background:var(--tp-violet);top:60%;right:10%;animation-delay:2s;"></div>
  <div class="tp-container py-5">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="tp-eyebrow">One Platform. Hundreds of Smart Tools.</span>
        <h1 class="hero-title mt-3">Smart Tools for Work, Business &amp; Everyday Life</h1>
        <p class="hero-sub mt-3">Free calculators, converters, generators and productivity tools designed for professionals, students and everyday users.</p>

        <form action="<?= tp_url('search') ?>" method="get" class="tp-search-shell mt-4" style="max-width:520px;">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q" placeholder="Search <?= (int) $totalTools ?>+ tools..." aria-label="Search tools">
          <button type="submit">Search</button>
        </form>

        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="<?= tp_url('all-tools') ?>" class="tp-btn tp-btn-light">Explore All Tools</a>
          <a href="#browse-profession" class="tp-btn tp-btn-outline">Browse Categories</a>
        </div>
      </div>

      <div class="col-lg-6 d-none d-lg-block">
        <div class="position-relative" style="padding:2rem 1rem;">
          <div class="tp-mockup-card mx-auto" style="max-width:380px;">
            <div class="tp-mockup-dots mb-3">
              <span style="background:#F87171;"></span><span style="background:#FBBF24;"></span><span style="background:#34D399;"></span>
            </div>
            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="tp-icon" style="width:38px;height:38px;"><i class="bi bi-calculator"></i></span>
              <div><strong>Loan EMI Calculator</strong><div class="text-muted small">Real-time result</div></div>
            </div>
            <div class="tp-mockup-row"><span class="text-muted">Loan Amount</span><span class="fw-semibold">$250,000</span></div>
            <div class="tp-mockup-row"><span class="text-muted">Interest Rate</span><span class="fw-semibold">7.5%</span></div>
            <div class="tp-mockup-row"><span class="text-muted">Tenure</span><span class="fw-semibold">360 months</span></div>
            <div class="rounded-3 p-3 mt-3 text-center" style="background:var(--tp-gradient-brand);color:#fff;">
              <div class="small text-white-50 text-uppercase">Monthly EMI</div>
              <div class="fs-3 fw-bold">$1,748.04</div>
            </div>
          </div>
          <div class="tp-mockup-float" style="top:-10px;right:0;">
            <i class="bi bi-check2-circle text-success"></i> Instant &amp; Free
          </div>
          <div class="tp-mockup-float" style="bottom:10px;left:-10px;animation-delay:1.5s;">
            <i class="bi bi-shield-check" style="color:var(--tp-indigo);"></i> No Signup Needed
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="tp-container">
  <div class="tp-stats-bar row g-3 text-center">
    <div class="col-4">
      <div class="stat-value" data-counter="<?= $totalTools ?>">0</div>
      <div class="stat-label">Tools</div>
    </div>
    <div class="col-4">
      <div class="stat-value" data-counter="<?= $totalCategories ?>">0</div>
      <div class="stat-label">Categories</div>
    </div>
    <div class="col-4">
      <div class="stat-value" data-counter="100">0</div>
      <div class="stat-label">% Free</div>
    </div>
  </div>
</div>

<section class="tp-section pt-5 pb-5">
  <div class="tp-container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal">
        <span class="tp-eyebrow">New — Free Sales CRM</span>
        <h2 class="tp-section-title mt-2">Run Your Whole Sales Process in One Place</h2>
        <p class="text-muted mt-3">Leads, customers, follow-up reminders, quotations &amp; invoices, khata/payment tracking, field visits and sales targets — a real CRM built for small businesses, distributors and field sales teams.</p>
        <ul class="list-unstyled mt-3 mb-4">
          <?php foreach ([
              'Sales pipeline board — drag leads through New → Contacted → Won',
              'WhatsApp-ready quotations, invoices &amp; payment reminders',
              'Customer khata/ledger with receivables aging',
              'GPS check-in for field visits, nearest-customer finder',
          ] as $point): ?>
          <li class="mb-2"><i class="bi bi-check-circle-fill" style="color:var(--tp-emerald);"></i> <?= $point ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex flex-wrap gap-3">
          <a href="<?= tp_url('crm/register.php') ?>" class="tp-btn tp-btn-primary">Start Free — No Card Needed</a>
          <a href="<?= tp_url('crm/login.php') ?>" class="tp-btn tp-btn-outline" style="color:var(--tp-indigo);border-color:var(--tp-indigo);">Log In</a>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block reveal">
        <div class="position-relative" style="padding:1.5rem 1rem;">
          <div class="tp-mockup-card mx-auto" style="max-width:400px;">
            <div class="tp-mockup-dots mb-3">
              <span style="background:#F87171;"></span><span style="background:#FBBF24;"></span><span style="background:#34D399;"></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong class="small">Sales Pipeline</strong>
              <span class="tp-badge tp-badge-popular">Rs 2.4M open</span>
            </div>
            <div class="tp-mockup-row"><span class="text-muted">🟣 Contacted</span><span class="fw-semibold">8 leads</span></div>
            <div class="tp-mockup-row"><span class="text-muted">🟠 Quotation Sent</span><span class="fw-semibold">5 leads</span></div>
            <div class="tp-mockup-row"><span class="text-muted">🟢 Won this month</span><span class="fw-semibold">14 leads</span></div>
            <div class="rounded-3 p-3 mt-3" style="background:var(--tp-gradient-brand);color:#fff;">
              <div class="small text-white-50">Next follow-up</div>
              <div class="fw-bold">📞 Call Ahmed — tomorrow 11:00 AM</div>
            </div>
          </div>
          <div class="tp-mockup-float" style="top:-6px;right:6px;">
            <i class="bi bi-whatsapp text-success"></i> WhatsApp Ready
          </div>
          <div class="tp-mockup-float" style="bottom:6px;left:-6px;animation-delay:1.5s;">
            <i class="bi bi-geo-alt" style="color:var(--tp-indigo);"></i> GPS Check-in
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="tp-section pt-4">
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
    <div class="row g-3">
      <?php foreach ([
          ['bi-lightning-charge', 'Fast', 'Instant client-side calculations — no waiting on a server round-trip.', '#7C3AED'],
          ['bi-gift', 'Free', 'Every tool is free to use, with no paywalls or hidden limits.', '#10B981'],
          ['bi-check2-circle', 'Accurate', 'Real, documented formulas — reviewed, not guessed.', '#5B21B6'],
          ['bi-incognito', 'No Registration', 'Use any tool instantly. No account required.', '#A78BFA'],
          ['bi-phone', 'Mobile Friendly', 'Every calculator is optimized for touch and small screens.', '#8B5CF6'],
          ['bi-shield-lock', 'Privacy Focused', 'Sensitive inputs never leave your browser unless you explicitly opt in.', '#6D28D9'],
      ] as $item): ?>
      <div class="col-6 col-md-4 col-lg-2 reveal">
        <div class="tp-card p-3 h-100 text-center">
          <div class="tp-icon mx-auto mb-2" style="background:color-mix(in srgb, <?= $item[3] ?> 14%, transparent);color:<?= $item[3] ?>;"><i class="bi <?= $item[0] ?>"></i></div>
          <h3 class="h6 fw-bold"><?= $item[1] ?></h3>
          <p class="text-muted small mb-0"><?= $item[2] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="tp-section tp-section-dark text-center">
  <div class="tp-container reveal">
    <h2 class="tp-section-title">Find the right tool for your next task.</h2>
    <a href="<?= tp_url('all-tools') ?>" class="tp-btn tp-btn-light mt-3">Explore All Tools</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
