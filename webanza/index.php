<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$rotating = lines(setting('hero_rotating')) ?: ['Digital Products'];
require __DIR__ . '/partials/header.php';
?>

<section class="hero" id="hero">
  <div class="hero-bg" data-parallax="0.4">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="grid-lines"></div>
    <canvas id="particles"></canvas>
    <div class="noise"></div>
  </div>

  <div class="container hero-grid">
    <div class="hero-copy" data-parallax="0.12">
      <div class="hero-badge" data-reveal="down"><b>NEW</b> <?= e(setting('hero_badge')) ?></div>
      <h1>
        <?= split_heading(setting('hero_title_1', 'We Build'), 'span') ?>
        <span class="rotator" aria-live="polite">
          <?php foreach ($rotating as $i => $word): ?><span class="<?= $i === 0 ? 'on' : '' ?>"><?= e($word) ?></span><?php endforeach; ?>
        </span>
        <?= split_heading(setting('hero_title_2', 'That Grow Your Business'), 'span') ?>
      </h1>
      <p class="hero-sub" data-reveal="up" style="--d:.35s"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-actions" data-reveal="up" style="--d:.5s">
        <a href="<?= e(url(setting('hero_btn1_link', 'packages.php'))) ?>" class="btn magnetic"><?= e(setting('hero_btn1_text', 'Explore Packages')) ?> <i class="fa-solid fa-arrow-right"></i></a>
        <a href="<?= e(url(setting('hero_btn2_link', 'contact.php'))) ?>" class="btn btn-ghost magnetic"><?= e(setting('hero_btn2_text', 'Get a Free Quote')) ?></a>
      </div>
      <div class="hero-trust" data-reveal="up" style="--d:.65s">
        <div class="avatars"><span>A</span><span>D</span><span>U</span><span>S</span></div>
        <div>
          <div class="stars">★★★★★</div>
          <small><?= e(setting('hero_rating_text')) ?></small>
        </div>
      </div>
    </div>

    <div class="hero-visual" id="heroVisual" data-reveal="zoom" style="--d:.3s">
      <div class="hv-layer browser" data-depth="0.25">
        <div class="browser-bar"><i></i><i></i><i></i><span><?= e(strtolower(str_replace(' ', '', setting('site_name', 'webanzatech')))) ?>.com</span></div>
        <div class="browser-body">
          <div class="bb-hero"><b></b><b></b><em></em></div>
          <div class="bb-card"><b></b><b></b></div>
          <div class="bb-card"><b></b><b></b></div>
          <div class="bb-card"><b></b><b></b></div>
        </div>
      </div>
      <div class="hv-layer phone" data-depth="0.6">
        <div class="ph-head">9:41 <span></span></div>
        <div class="ph-banner"></div>
        <div class="ph-grid"><i></i><i></i><i></i><i></i></div>
        <div class="ph-nav"><i class="fa-solid fa-house"></i><i class="fa-solid fa-magnifying-glass"></i><i class="fa-solid fa-bag-shopping"></i><i class="fa-regular fa-user"></i></div>
      </div>
      <div class="hv-layer orbit" data-depth="0.4">
        <i class="fa-brands fa-apple"></i><i class="fa-brands fa-android"></i><i class="fa-brands fa-google"></i><i class="fa-brands fa-shopify"></i>
      </div>
      <div class="hv-layer float-card fc-1" data-depth="0.9">
        <span class="fi"><i class="fa-solid fa-arrow-trend-up"></i></span>
        <div><strong>+320%</strong><small>Organic traffic</small></div>
      </div>
      <div class="hv-layer float-card fc-2" data-depth="0.7">
        <span class="fi"><i class="fa-solid fa-cart-shopping"></i></span>
        <div><strong>1,248</strong><small>Orders this month</small></div>
      </div>
      <div class="hv-layer float-card fc-3" data-depth="1.1">
        <span class="fi"><i class="fa-solid fa-mobile-screen"></i></span>
        <div><strong>iOS &amp; Android</strong><small>Live on both stores</small></div>
      </div>
    </div>
  </div>
  <a href="#services" class="scroll-down" aria-label="Scroll down"><span></span>Scroll</a>
</section>

<?php
section_bands();
if (setting_on('home_show_services'))     { section_services(); }
if (setting_on('home_show_stats'))        { section_stats(); }
if (setting_on('home_show_about'))        { section_about(); }
if (setting_on('home_show_packages'))     { section_packages(true, false); }
if (setting_on('home_show_process'))      { section_process(); }
if (setting_on('home_show_portfolio'))    { section_portfolio(4); }
if (setting_on('home_show_ceo'))          { section_ceo(); }
if (setting_on('home_show_team'))         { section_team(); }
if (setting_on('home_show_clients'))      { section_clients(); }
if (setting_on('home_show_testimonials')) { section_testimonials(); }
if (setting_on('home_show_faq'))          { section_faq(); }
if (setting_on('home_show_blog'))         { section_blog(3); }
section_cta();

require __DIR__ . '/partials/footer.php';
