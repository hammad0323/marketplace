<?php
/**
 * Admin bootstrap + layout helpers. Every admin page requires this file.
 */
require dirname(__DIR__) . '/config.php';
require __DIR__ . '/entities.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function admin_header(string $title, string $active = ''): void
{
    $me = require_admin();
    $newInquiries = (int) val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
    $groups = [
        'Content'  => ['services', 'package_categories', 'packages', 'portfolio', 'posts', 'pages'],
        'Sections' => ['team', 'testimonials', 'faqs', 'clients', 'stats', 'process_steps'],
    ];
    $ents = entities();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Admin · <?= e(setting('site_name', 'Webanza Tech')) ?></title>
<link rel="icon" href="<?= e(media(setting('favicon', 'assets/img/favicon.png'))) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url('admin/')) ?>"><img src="<?= e(media(setting('logo_light', 'assets/img/logo-light.png'))) ?>" alt=""></a>
  <nav>
    <a href="<?= e(url('admin/')) ?>" class="<?= $active === 'dashboard' ? 'on' : '' ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="<?= e(url('admin/inquiries.php')) ?>" class="<?= $active === 'inquiries' ? 'on' : '' ?>"><i class="fa-solid fa-inbox"></i> Inquiries &amp; Orders <?php if ($newInquiries): ?><b class="count"><?= $newInquiries ?></b><?php endif; ?></a>
    <?php foreach ($groups as $g => $keys): ?>
      <span class="nav-label"><?= e($g) ?></span>
      <?php foreach ($keys as $k): ?>
        <a href="<?= e(url('admin/manage.php?e=' . $k)) ?>" class="<?= $active === $k ? 'on' : '' ?>"><i class="<?= e($ents[$k]['icon']) ?>"></i> <?= e($ents[$k]['label']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <span class="nav-label">Website</span>
    <a href="<?= e(url('admin/settings.php')) ?>" class="<?= $active === 'settings' ? 'on' : '' ?>"><i class="fa-solid fa-sliders"></i> Site Settings</a>
    <a href="<?= e(url('admin/subscribers.php')) ?>" class="<?= $active === 'subscribers' ? 'on' : '' ?>"><i class="fa-solid fa-at"></i> Subscribers</a>
    <a href="<?= e(url('admin/users.php')) ?>" class="<?= $active === 'users' ? 'on' : '' ?>"><i class="fa-solid fa-user-shield"></i> Admin Users</a>
  </nav>
</aside>
<div class="main">
  <header class="topbar">
    <button class="menu-btn" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <h1><?= e($title) ?></h1>
    <div class="top-actions">
      <a class="btn btn-light" href="<?= e(url()) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> <span>View site</span></a>
      <div class="me">
        <span class="avatar"><?= e(initials($me['name'])) ?></span>
        <span class="me-name"><?= e($me['name']) ?></span>
        <a href="<?= e(url('admin/logout.php')) ?>" title="Log out" class="logout"><i class="fa-solid fa-right-from-bracket"></i></a>
      </div>
    </div>
  </header>
  <div class="content">
    <?php foreach (flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><i class="fa-solid <?= $f['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= e($f['message']) ?></div>
    <?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<div class="nav-backdrop" onclick="document.body.classList.remove('nav-open')"></div>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

/** Stops a POST that lacks a valid CSRF token. */
function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function status_badge(string $status): string
{
    $labels = ['new' => 'New', 'contacted' => 'Contacted', 'in_progress' => 'In progress', 'won' => 'Won', 'lost' => 'Lost'];
    return '<span class="badge badge-' . e($status) . '">' . e($labels[$status] ?? $status) . '</span>';
}
