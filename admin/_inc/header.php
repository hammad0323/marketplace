<?php
$admin = current_admin();
$title = $GLOBALS['admin_title'] ?? 'Admin';
$active = $GLOBALS['admin_active'] ?? '';
$newOrders = can('orders.view') ? (int) db_val("SELECT COUNT(*) FROM orders WHERE status = 'pending'") : 0;
$newMessages = can('messages.view') ? (int) db_val("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'") : 0;
$pendingReviews = can('reviews.manage') ? (int) db_val("SELECT COUNT(*) FROM reviews WHERE status = 'pending'") : 0;
$badges = ['orders' => $newOrders, 'messages' => $newMessages, 'reviews' => $pendingReviews];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · <?= e(setting('site_name', 'Beglet')) ?> Admin</title>
<link rel="icon" href="<?= e(media_url(setting('favicon_path', 'assets/img/brand/favicon.svg'))) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if ($admin): ?>
<aside class="admin-sidebar offcanvas-lg offcanvas-start" id="adminSidebar" tabindex="-1">
  <div class="admin-sidebar__brand">
    <a href="<?= e(admin_url()) ?>"><span>BEGLET</span><small>Admin</small></a>
    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
  </div>
  <nav class="admin-nav">
    <?php foreach (admin_menu() as $item):
        [$label, $icon, $link, $perm] = $item;
        $children = $item[4] ?? null;
        if ($children) {
            $children = array_values(array_filter($children, fn($c) => can($c[2])));
            if (!$children) continue;
            $open = in_array($active, array_column($children, 1), true); ?>
            <div class="admin-nav__group">
              <button class="admin-nav__head<?= $open ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#nav-<?= e(slugify($label)) ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>"><i class="bi bi-<?= e($icon) ?>"></i> <?= e($label) ?> <i class="bi bi-chevron-down ms-auto small"></i></button>
              <div class="collapse<?= $open ? ' show' : '' ?>" id="nav-<?= e(slugify($label)) ?>">
                <?php foreach ($children as [$cl, $cu]): ?>
                  <a class="admin-nav__sub<?= $active === $cu ? ' is-active' : '' ?>" href="<?= e(admin_url($cu)) ?>"><?= e($cl) ?><?php if (!empty($badges[$cu])): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= (int) $badges[$cu] ?></span><?php endif; ?></a>
                <?php endforeach; ?>
              </div>
            </div>
        <?php } elseif (can($perm)) { ?>
            <a class="admin-nav__link<?= $active === $link ? ' is-active' : '' ?>" href="<?= e(admin_url($link === 'index' ? '' : $link)) ?>"><i class="bi bi-<?= e($icon) ?>"></i> <?= e($label) ?><?php if (!empty($badges[$link])): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= (int) $badges[$link] ?></span><?php endif; ?></a>
        <?php } endforeach; ?>
  </nav>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <button class="btn btn-link d-lg-none p-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-label="Menu"><i class="bi bi-list fs-4"></i></button>
    <h1 class="admin-topbar__title"><?= e($title) ?></h1>
    <div class="ms-auto d-flex align-items-center gap-3">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(path_url('/')) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> <span class="d-none d-sm-inline">View store</span></a>
      <div class="dropdown">
        <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= e($admin['name']) ?></span></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted"><?= e($admin['role_name']) ?></span></li>
          <li><a class="dropdown-item" href="<?= e(admin_url('profile')) ?>">My profile & password</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="dropdown-item">Sign out</button></form></li>
        </ul>
      </div>
    </div>
  </header>
  <div class="admin-content">
    <?php if (app_key_is_default() && is_super_admin()): ?><div class="alert alert-danger"><i class="bi bi-exclamation-octagon"></i> <strong>APP_KEY is not set.</strong> Generate a unique key in your config file before saving payment credentials. See docs/DEPLOYMENT.md.</div><?php endif; ?>
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e(['error' => 'danger', 'success' => 'success', 'warning' => 'warning'][$f['type']] ?? 'info') ?> alert-dismissible fade show"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; ?>
<?php else: ?>
<div class="admin-auth">
<?php endif; ?>
