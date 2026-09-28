<?php
/**
 * Public site header. Pages may set before including:
 *   $page_title, $page_desc, $page_image
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$site_name  = setting('site_name', 'Webanza Tech');
$meta_title = !empty($page_title) ? $page_title . ' | ' . $site_name : setting('meta_title', $site_name);
$meta_desc  = !empty($page_desc) ? $page_desc : setting('meta_description');
$meta_img   = media(!empty($page_image) ? $page_image : (setting('og_image') ?: setting('logo', 'assets/img/logo.png')));
$nav_services = rows('SELECT title, slug, icon, short_desc FROM services WHERE is_active = 1 ORDER BY sort_order, id');
$cur = current_page();

$hex = fn(string $k, string $d) => preg_match('~^#[0-9a-fA-F]{3,8}$~', setting($k, $d)) ? setting($k, $d) : $d;
$announcement = setting('announcement');
?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta_title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<meta name="keywords" content="<?= e(setting('meta_keywords')) ?>">
<meta property="og:title" content="<?= e($meta_title) ?>">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<meta property="og:image" content="<?= e($meta_img) ?>">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="<?= e($hex('color_dark', '#0a1424')) ?>">
<link rel="icon" href="<?= e(media(setting('favicon', 'assets/img/favicon.png'))) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&family=Caveat:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<style>:root{--primary:<?= e($hex('color_primary', '#1f9d55')) ?>;--accent:<?= e($hex('color_accent', '#3ee089')) ?>;--dark:<?= e($hex('color_dark', '#0a1424')) ?>}</style>
<script>document.documentElement.classList.replace('no-js','js');</script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $site_name,
    'url'      => url(),
    'logo'     => media(setting('logo', 'assets/img/logo.png')),
    'email'    => setting('email'),
    'telephone'=> setting('phone'),
    'sameAs'   => array_column(social_links(), 'url'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php if (setting('ga_id') !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(setting('ga_id')) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode(setting('ga_id'), JSON_HEX_TAG) ?>);</script>
<?php endif; ?>
<?= setting('head_code') /* admin-controlled custom code (pixels, verification tags) */ ?>
</head>
<body>

<div class="preloader" id="preloader" aria-hidden="true">
  <div class="preloader-inner">
    <img src="<?= e(media(setting('logo_light', 'assets/img/logo-light.png'))) ?>" alt="">
    <div class="preloader-bar"><span></span></div>
  </div>
</div>
<div class="scroll-progress" id="scrollProgress"></div>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<?php if ($announcement !== ''): ?>
<div class="topbar" id="topbar">
  <?php if (setting('announcement_link') !== ''): ?>
    <a href="<?= e(url(setting('announcement_link'))) ?>"><?= e($announcement) ?></a>
  <?php else: ?>
    <?= e($announcement) ?>
  <?php endif; ?>
  <button class="topbar-close" type="button" aria-label="Close announcement">&times;</button>
</div>
<?php endif; ?>

<header class="header" id="header">
  <div class="container">
    <a href="<?= e(url()) ?>" class="logo" aria-label="<?= e($site_name) ?> home">
      <img class="logo-light" src="<?= e(media(setting('logo_light', 'assets/img/logo-light.png'))) ?>" alt="<?= e($site_name) ?>">
      <img class="logo-dark" src="<?= e(media(setting('logo', 'assets/img/logo.png'))) ?>" alt="<?= e($site_name) ?>">
    </a>
    <nav class="nav" aria-label="Main">
      <a href="<?= e(url()) ?>" class="<?= $cur === 'index' ? 'active' : '' ?>">Home</a>
      <a href="<?= e(url('about.php')) ?>" class="<?= $cur === 'about' ? 'active' : '' ?>">About</a>
      <div class="nav-drop">
        <a href="<?= e(url('services.php')) ?>" class="<?= in_array($cur, ['services', 'service'], true) ? 'active' : '' ?>">Services <i class="fa-solid fa-chevron-down"></i></a>
        <div class="mega">
          <?php foreach ($nav_services as $s): ?>
            <a href="<?= e(url('service.php?slug=' . $s['slug'])) ?>">
              <span class="mi"><i class="<?= e($s['icon']) ?>"></i></span>
              <span><strong><?= e($s['title']) ?></strong><small><?= e(excerpt($s['short_desc'], 60)) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= e(url('packages.php')) ?>" class="<?= $cur === 'packages' ? 'active' : '' ?>">Packages</a>
      <a href="<?= e(url('portfolio.php')) ?>" class="<?= in_array($cur, ['portfolio', 'project'], true) ? 'active' : '' ?>">Portfolio</a>
      <a href="<?= e(url('blog.php')) ?>" class="<?= in_array($cur, ['blog', 'post'], true) ? 'active' : '' ?>">Blog</a>
      <a href="<?= e(url('contact.php')) ?>" class="<?= $cur === 'contact' ? 'active' : '' ?>">Contact</a>
    </nav>
    <div class="header-cta">
      <a href="<?= e(url('contact.php')) ?>" class="btn btn-sm magnetic">Get a Quote <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <button class="burger" id="burger" type="button" aria-label="Open menu" aria-expanded="false"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="mobile-menu" id="mobileMenu">
  <a class="mm-link" href="<?= e(url()) ?>">Home</a>
  <a class="mm-link" href="<?= e(url('about.php')) ?>">About</a>
  <a class="mm-link" href="<?= e(url('services.php')) ?>">Services</a>
  <div class="mm-services">
    <?php foreach ($nav_services as $s): ?>
      <a href="<?= e(url('service.php?slug=' . $s['slug'])) ?>"><i class="<?= e($s['icon']) ?>"></i><?= e($s['title']) ?></a>
    <?php endforeach; ?>
  </div>
  <a class="mm-link" href="<?= e(url('packages.php')) ?>">Packages</a>
  <a class="mm-link" href="<?= e(url('portfolio.php')) ?>">Portfolio</a>
  <a class="mm-link" href="<?= e(url('blog.php')) ?>">Blog</a>
  <a class="mm-link" href="<?= e(url('contact.php')) ?>">Contact</a>
  <div class="mm-bottom">
    <a href="<?= e(url('contact.php')) ?>" class="btn">Get a Free Quote <i class="fa-solid fa-arrow-right"></i></a>
    <?php if (setting('email')): ?><a href="mailto:<?= e(setting('email')) ?>"><i class="fa-solid fa-envelope"></i> <?= e(setting('email')) ?></a><?php endif; ?>
    <div class="socials">
      <?php foreach (social_links() as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['name']) ?>"><i class="<?= e($s['icon']) ?>"></i></a><?php endforeach; ?>
    </div>
  </div>
</div>

<main id="main">
