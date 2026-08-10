<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
require_login('admin');
$admin = current_user($conn);
$adminPageTitle = $adminPageTitle ?? 'Dashboard';
$adminActive = $adminActive ?? '';

$adminNav = [
    ['group' => 'Overview', 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => '/admin/index.php', 'ready' => true],
    ]],
    ['group' => 'Marketplace', 'items' => [
        ['key' => 'providers', 'label' => 'Providers', 'icon' => 'bi-shop', 'href' => '/admin/providers.php', 'ready' => true],
        ['key' => 'customers', 'label' => 'Customers', 'icon' => 'bi-people', 'href' => '/admin/customers.php', 'ready' => true],
        ['key' => 'categories', 'label' => 'Categories', 'icon' => 'bi-grid', 'href' => '/admin/categories.php', 'ready' => true],
        ['key' => 'cities', 'label' => 'Cities', 'icon' => 'bi-geo-alt', 'href' => '/admin/cities.php', 'ready' => true],
        ['key' => 'services', 'label' => 'Services', 'icon' => 'bi-list-ul', 'href' => '/admin/services.php', 'ready' => true],
    ]],
    ['group' => 'Bookings & Finance', 'items' => [
        ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'bi-calendar-check', 'href' => '/admin/bookings.php', 'ready' => true],
        ['key' => 'payments', 'label' => 'Payments', 'icon' => 'bi-credit-card', 'href' => '#', 'ready' => false],
        ['key' => 'memberships', 'label' => 'Memberships', 'icon' => 'bi-award', 'href' => '#', 'ready' => false],
    ]],
    ['group' => 'Content', 'items' => [
        ['key' => 'trips', 'label' => 'Trip Planner', 'icon' => 'bi-map', 'href' => '#', 'ready' => false],
        ['key' => 'reviews', 'label' => 'Reviews', 'icon' => 'bi-star', 'href' => '#', 'ready' => false],
        ['key' => 'blog', 'label' => 'Blog & Guides', 'icon' => 'bi-journal-text', 'href' => '#', 'ready' => false],
        ['key' => 'cms', 'label' => 'Homepage CMS', 'icon' => 'bi-layout-text-window', 'href' => '#', 'ready' => false],
    ]],
    ['group' => 'System', 'items' => [
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear', 'href' => '/admin/settings.php', 'ready' => true],
        ['key' => 'seo', 'label' => 'SEO', 'icon' => 'bi-search', 'href' => '#', 'ready' => false],
        ['key' => 'emails', 'label' => 'Email Templates', 'icon' => 'bi-envelope', 'href' => '/admin/emails.php', 'ready' => true],
        ['key' => 'logs', 'label' => 'Activity Logs', 'icon' => 'bi-clock-history', 'href' => '#', 'ready' => false],
    ]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($adminPageTitle); ?> — Admin — <?php echo e(APP_NAME); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="/admin/index.php" class="brand"><span class="brand-mark"><i class="bi bi-compass"></i></span><?php echo e(APP_NAME); ?></a>
    <?php foreach ($adminNav as $group): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label"><?php echo e($group['group']); ?></div>
        <div class="admin-nav">
          <?php foreach ($group['items'] as $item): ?>
            <a href="<?php echo $item['ready'] ? e($item['href']) : 'javascript:void(0)'; ?>"
               class="<?php echo $adminActive === $item['key'] ? 'active' : ''; ?><?php echo !$item['ready'] ? ' disabled' : ''; ?>">
              <i class="bi <?php echo e($item['icon']); ?>"></i> <?php echo e($item['label']); ?>
              <?php if (!$item['ready']): ?><span class="soon-tag">Soon</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-menu-toggle btn-w btn-ghost btn-sm" style="display:none;"><i class="bi bi-list"></i></button>
        <h1><?php echo e($adminPageTitle); ?></h1>
      </div>
      <div style="display:flex;align-items:center;gap:14px;">
        <a href="/index.php" class="btn-w btn-outline btn-sm" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View site</a>
        <div class="user-chip" style="cursor:default;">
          <span class="avatar-dot"><?php echo e(strtoupper(substr($admin['name'], 0, 1))); ?></span>
          <?php echo e($admin['name']); ?>
        </div>
        <a href="/admin/logout.php" class="btn-w btn-ghost btn-sm" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
    <div class="admin-content">
