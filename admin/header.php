<?php
/**
 * admin/header.php — sidebar + topbar chrome for every /admin/*.php page.
 * Expects $pageTitle and $activePage to be set by the including page.
 */
$businessId = wh_current_business_id();
$admin = wh_current_admin();
$role = wh_admin_role();
$allowed = WH_ROLE_PERMISSIONS[$role] ?? [];
$can = fn($key) => in_array('*', $allowed, true) || in_array($key, $allowed, true);
$unreadCount = $businessId ? wh_unread_notification_count($businessId) : 0;
$activePage = $activePage ?? '';
$settings = $businessId ? wh_get_settings($businessId) : [];
$siteName = $settings['site_name'] ?? 'Wedding Hall';

function wh_nav_link($href, $icon, $label, $key, $activePage)
{
    $cls = $activePage === $key ? 'active' : '';
    echo '<a href="' . e($href) . '" class="' . $cls . '"><i class="fa-solid ' . e($icon) . '"></i> ' . e($label) . '</a>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> · Admin · <?= e($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><?= e($siteName) ?><span class="sub">Admin Panel</span></div>

    <div class="admin-nav-group">
      <?php wh_nav_link(BASE_URL . '/admin/', 'fa-gauge', 'Dashboard', 'dashboard', $activePage); ?>
    </div>

    <?php if ($can('bookings')): ?>
    <div class="admin-nav-group">
      <h6>Bookings</h6>
      <?php wh_nav_link(BASE_URL . '/admin/bookings.php', 'fa-list-check', 'All Bookings', 'bookings', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/booking-form.php', 'fa-circle-plus', 'Add Booking', 'booking-form', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/calendar.php', 'fa-calendar-days', 'Calendar', 'calendar', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/date-search.php', 'fa-magnifying-glass', 'Date Search', 'date-search', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('halls')): ?>
    <div class="admin-nav-group">
      <h6>Halls</h6>
      <?php wh_nav_link(BASE_URL . '/admin/halls.php', 'fa-building-columns', 'All Halls', 'halls', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/hall-form.php', 'fa-circle-plus', 'Add Hall', 'hall-form', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/time-slots.php', 'fa-clock', 'Time Slots', 'time-slots', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/event-types.php', 'fa-champagne-glasses', 'Event Types', 'event-types', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('customers')): ?>
    <div class="admin-nav-group">
      <h6>Customers</h6>
      <?php wh_nav_link(BASE_URL . '/admin/customers.php', 'fa-users', 'All Customers', 'customers', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('payments')): ?>
    <div class="admin-nav-group">
      <h6>Payments</h6>
      <?php wh_nav_link(BASE_URL . '/admin/payments.php', 'fa-money-bill-wave', 'Payment History', 'payments', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('gallery')): ?>
    <div class="admin-nav-group">
      <h6>Gallery</h6>
      <?php wh_nav_link(BASE_URL . '/admin/gallery.php', 'fa-images', 'Gallery Images', 'gallery', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('reports')): ?>
    <div class="admin-nav-group">
      <h6>Reports</h6>
      <?php wh_nav_link(BASE_URL . '/admin/reports.php', 'fa-chart-line', 'Reports', 'reports', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($can('chatbot')): ?>
    <div class="admin-nav-group">
      <h6>Chatbot</h6>
      <?php wh_nav_link(BASE_URL . '/admin/chatbot.php', 'fa-robot', 'Database Assistant', 'chatbot', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($role === 'admin' || $role === 'super_admin'): ?>
    <div class="admin-nav-group">
      <h6>Website</h6>
      <?php wh_nav_link(BASE_URL . '/admin/pages.php', 'fa-file-lines', 'Pages &amp; FAQ', 'pages', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/blog.php', 'fa-newspaper', 'Blog', 'blog', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/seo.php', 'fa-magnifying-glass-chart', 'SEO', 'seo', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/settings.php', 'fa-gear', 'Settings', 'settings', $activePage); ?>
      <?php wh_nav_link(BASE_URL . '/admin/users.php', 'fa-user-shield', 'Admin Users', 'users', $activePage); ?>
    </div>
    <?php endif; ?>

    <?php if ($role === 'super_admin'): ?>
    <div class="admin-nav-group">
      <h6>Platform (Super Admin)</h6>
      <?php wh_nav_link(BASE_URL . '/admin/businesses.php', 'fa-building', 'Businesses', 'businesses', $activePage); ?>
    </div>
    <?php endif; ?>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex;align-items:center;gap:14px;">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <span class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
      </div>
      <div class="topbar-right">
        <div class="notif-wrap">
          <button class="notif-btn" title="Notifications">
            <i class="fa-solid fa-bell"></i>
            <?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
          </button>
          <div class="notif-dropdown">
            <?php $notifs = $businessId ? wh_unread_notifications($businessId, 8) : []; ?>
            <?php if (!$notifs): ?><div class="notif-item">No notifications yet.</div><?php endif; ?>
            <?php foreach ($notifs as $n): ?>
              <a href="<?= e(BASE_URL . ($n['link'] ?: '/admin/')) ?>" style="color:inherit;">
                <div class="notif-item">
                  <strong><?= e($n['title']) ?></strong>
                  <?php if ($n['message']): ?><div><?= e($n['message']) ?></div><?php endif; ?>
                  <span class="time"><?= wh_format_date($n['created_at'], 'd M, g:i A') ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="admin-user">
          <div class="avatar-circle"><?= e(strtoupper(substr($admin['name'] ?? 'A', 0, 1))) ?></div>
          <div>
            <div style="font-weight:600;"><?= e($admin['name'] ?? '') ?></div>
            <div style="font-size:.74rem;color:var(--a-muted);text-transform:capitalize;"><?= e(str_replace('_', ' ', $role)) ?></div>
          </div>
          <a href="<?= e(BASE_URL) ?>/admin/logout.php" class="btn btn-light btn-sm" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
      </div>
    </div>
    <div class="admin-content">
    <?php $wh_flashes = wh_flash_get(); if ($wh_flashes): foreach ($wh_flashes as $wh_flash): ?>
      <div class="alert alert-<?= e($wh_flash['type']) ?>" data-autodismiss><?= e($wh_flash['message']) ?></div>
    <?php endforeach; endif; ?>
