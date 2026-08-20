<?php
/**
 * header.php — shared <head> + navbar for every public page.
 *
 * Pages set these BEFORE requiring this file (all optional):
 *   $pageTitle, $pageDescriptionFallback, $pageSeo (row from seo_settings),
 *   $pageSchemas (array of extra generate_schema() HTML strings),
 *   $breadcrumbItems (array for generate_breadcrumbs()), $bodyClass
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

$pageTitle = $pageTitle ?? tp_setting('site_name') . ' — ' . tp_setting('site_tagline');
$pageDescriptionFallback = $pageDescriptionFallback ?? tp_setting('site_tagline');
$pageSeo = $pageSeo ?? [];
$pageSchemas = $pageSchemas ?? [generate_schema('WebSite', []), generate_schema('Organization', [])];
// REQUEST_URI already includes the base path (e.g. /tools/...) exactly as
// the browser sent it, so we only need to add the scheme+host — prepending
// tp_url() on top would double the base path in a subfolder deployment.
$currentUrl = tp_site_origin() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

$navCategories = get_categories(true);
?>
<!DOCTYPE html>
<html lang="en" data-theme-default="<?= e(tp_setting('default_theme')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className += ' js';</script>
<?= generate_meta_tags($pageSeo, $pageTitle, $pageDescriptionFallback, $currentUrl) ?>
<?php foreach ($pageSchemas as $schema) { echo $schema; } ?>
<?php if ($breadcrumbSchema = $breadcrumbItems ?? null): $bc = generate_breadcrumbs($breadcrumbItems); ?>
<?= $bc['schema'] ?>
<?php endif; ?>
<link rel="icon" href="<?= e(tp_setting('favicon') ?: tp_asset('images/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<?php if ($css = tp_setting('custom_css')): ?><style><?= $css /* admin-controlled, trusted input */ ?></style><?php endif; ?>
<?php if ($ga = tp_setting('google_analytics_id')): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
<?php endif; ?>
<?php if ($hs = tp_setting('header_scripts')): ?><?= $hs /* admin-controlled, trusted input */ ?><?php endif; ?>
</head>
<body data-base-url="<?= e(TOOLS_PLATFORM_URL) ?>" class="<?= e($bodyClass ?? '') ?>">

<?php if (tp_setting('maintenance_mode') === '1' && !tp_is_admin_logged_in()): ?>
<div class="tp-container py-5 text-center">
    <h1 class="hero-title" style="color:var(--tp-text)">We'll be right back</h1>
    <p class="lead"><?= e(tp_setting('maintenance_message', 'The site is undergoing scheduled maintenance.')) ?></p>
</div>
</body></html>
<?php exit; endif; ?>

<header class="tp-navbar">
  <div class="tp-container d-flex align-items-center justify-content-between py-2">
    <a href="<?= tp_url() ?>" class="brand d-flex align-items-center gap-2 text-decoration-none">
      <i class="bi bi-grid-1x2-fill"></i> <?= e(tp_setting('site_name')) ?>
    </a>

    <nav class="d-none d-lg-flex align-items-center gap-1">
      <a href="<?= tp_url() ?>" class="tp-nav-link">Home</a>
      <div class="dropdown">
        <a href="#" class="tp-nav-link dropdown-toggle" data-bs-toggle="dropdown">Categories</a>
        <div class="dropdown-menu tp-mega p-4" style="min-width:720px;">
          <div class="row g-4">
          <?php foreach (array_chunk($navCategories, (int) ceil(count($navCategories) / 3) ?: 1) as $col): ?>
            <div class="col-4 tp-mega-col">
              <?php foreach ($col as $cat): ?>
                <a href="<?= tp_url($cat['slug']) ?>"><i class="bi <?= e($cat['icon']) ?> me-1"></i> <?= e($cat['name']) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
      <a href="<?= tp_url('popular') ?>" class="tp-nav-link">Popular</a>
      <a href="<?= tp_url('trending') ?>" class="tp-nav-link">Trending</a>
      <a href="<?= tp_url('new-tools') ?>" class="tp-nav-link">New Tools</a>
      <a href="<?= tp_url('blog') ?>" class="tp-nav-link">Blog</a>
      <a href="<?= tp_url('about') ?>" class="tp-nav-link">About</a>
    </nav>

    <div class="d-flex align-items-center gap-2">
      <button class="theme-toggle-btn" data-theme-toggle title="Toggle theme"><span data-theme-icon>🖥️</span></button>
      <button class="btn btn-sm d-none d-md-inline-flex align-items-center gap-1" style="border:1px solid var(--tp-border);border-radius:999px;" data-search-open>
        <i class="bi bi-search"></i> Search tools
      </button>
      <button class="btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#tpMobileNav"><i class="bi bi-list fs-4"></i></button>
    </div>
  </div>
</header>

<div class="offcanvas offcanvas-end" id="tpMobileNav">
  <div class="offcanvas-header"><h5>Menu</h5><button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body d-flex flex-column gap-1">
    <a href="<?= tp_url() ?>" class="tp-nav-link">Home</a>
    <?php foreach ($navCategories as $cat): ?>
      <a href="<?= tp_url($cat['slug']) ?>" class="tp-nav-link"><i class="bi <?= e($cat['icon']) ?> me-1"></i><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= tp_url('blog') ?>" class="tp-nav-link">Blog</a>
    <a href="<?= tp_url('about') ?>" class="tp-nav-link">About</a>
  </div>
</div>

<div id="tpSearchOverlay" class="tp-search-overlay">
  <div class="tp-container pt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="text-white m-0">Search tools</h4>
      <button class="btn btn-light rounded-circle" data-search-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tp-search-shell">
      <i class="bi bi-search text-muted"></i>
      <input type="text" data-search-input placeholder="Search calculators, converters, generators...">
    </div>
    <div class="mt-4 bg-white rounded-4 p-3" data-search-results style="max-height:60vh;overflow:auto;"></div>
  </div>
</div>
<style>
.tp-search-overlay{position:fixed;inset:0;background:rgba(7,11,22,.92);z-index:2000;display:none;overflow:auto;}
.tp-search-overlay.show{display:block;}
.search-result-item{display:flex;gap:.8rem;align-items:center;padding:.7rem;border-radius:10px;color:var(--tp-text);}
.search-result-item:hover{background:var(--tp-surface-2);}
.search-result-item .tp-icon{width:36px;height:36px;border-radius:8px;background:var(--tp-gradient-cyan);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
</style>

<?php foreach (tp_flash_get() as $flash): ?>
<div class="tp-container mt-3">
  <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endforeach; ?>

<?php if (!empty($breadcrumbItems)): ?>
<div class="tp-container mt-3"><?= $bc['html'] ?? generate_breadcrumbs($breadcrumbItems)['html'] ?></div>
<?php endif; ?>
