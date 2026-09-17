<?php
/**
 * header.php — shared public-site chrome.
 * Expects (all optional): $pageTitle, $metaDescription, $activeNav, $seoPageKey, $bodyClass
 */
$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
$seo = $seoPageKey ?? null ? wh_fetch_one('SELECT * FROM seo_settings WHERE business_id=? AND page_key=?', 'is', [$businessId, $seoPageKey]) : null;

$siteName = $settings['site_name'] ?? 'Wedding Hall';
$title = $seo['seo_title'] ?? ($pageTitle ?? $siteName) . ' | ' . $siteName;
if (!empty($pageTitle) && empty($seo['seo_title'])) {
    $title = $pageTitle . ' | ' . $siteName;
} elseif (empty($pageTitle) && empty($seo['seo_title'])) {
    $title = $siteName . ' — ' . ($settings['tagline'] ?? '');
}
$description = $seo['meta_description'] ?? ($metaDescription ?? $settings['tagline'] ?? '');
$primaryColor = $settings['primary_color'] ?? '#7a1f3d';
$secondaryColor = $settings['secondary_color'] ?? '#c79a4b';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($seo['robots'] ?? 'index,follow') ?>">
<link rel="canonical" href="<?= e(BASE_URL . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<?php if (!empty($seo['og_image'])): ?><meta property="og:image" content="<?= e(BASE_URL . '/' . $seo['og_image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if (!empty($settings['favicon'])): ?><link rel="icon" href="<?= e(BASE_URL . '/' . $settings['favicon']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/global.css">
<script>document.documentElement.classList.add('js');</script>
<style>:root{--primary:<?= e($primaryColor) ?>;--secondary:<?= e($secondaryColor) ?>;}</style>
<?php if (!empty($settings['ga_id'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($settings['ga_id']) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($settings['ga_id']) ?>');</script>
<?php endif; ?>
<?php if (!empty($settings['gsc_verification'])): ?><meta name="google-site-verification" content="<?= e($settings['gsc_verification']) ?>"><?php endif; ?>
<?php if (!empty($settings['bing_verification'])): ?><meta name="msvalidate.01" content="<?= e($settings['bing_verification']) ?>"><?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="site-header">
  <nav class="nav">
    <a href="<?= e(BASE_URL) ?>/" class="nav-brand">
      <?php if (!empty($settings['logo'])): ?><img src="<?= e(BASE_URL . '/' . $settings['logo']) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?><?= e($siteName) ?><?php endif; ?>
    </a>
    <ul class="nav-links">
      <li><a href="<?= e(BASE_URL) ?>/" class="<?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?= e(BASE_URL) ?>/halls" class="<?= ($activeNav ?? '') === 'halls' ? 'active' : '' ?>">Halls</a></li>
      <li><a href="<?= e(BASE_URL) ?>/gallery" class="<?= ($activeNav ?? '') === 'gallery' ? 'active' : '' ?>">Gallery</a></li>
      <li><a href="<?= e(BASE_URL) ?>/availability" class="<?= ($activeNav ?? '') === 'availability' ? 'active' : '' ?>">Availability</a></li>
      <li><a href="<?= e(BASE_URL) ?>/about" class="<?= ($activeNav ?? '') === 'about' ? 'active' : '' ?>">About</a></li>
      <li><a href="<?= e(BASE_URL) ?>/blog" class="<?= ($activeNav ?? '') === 'blog' ? 'active' : '' ?>">Blog</a></li>
      <li><a href="<?= e(BASE_URL) ?>/contact" class="<?= ($activeNav ?? '') === 'contact' ? 'active' : '' ?>">Contact</a></li>
    </ul>
    <div class="nav-cta">
      <a href="<?= e(BASE_URL) ?>/booking" class="btn btn-primary btn-sm">Book Now</a>
      <button class="nav-toggle" aria-label="Menu" aria-expanded="false">&#9776;</button>
    </div>
  </nav>
</header>
<main>
<?php $wh_flashes = wh_flash_get(); if ($wh_flashes): ?>
  <div class="container" style="padding-top:20px;">
    <?php foreach ($wh_flashes as $wh_flash): ?>
      <div class="alert alert-<?= e($wh_flash['type']) ?>" data-autodismiss><?= e($wh_flash['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
