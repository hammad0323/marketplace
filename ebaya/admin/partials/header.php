<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
$__me = current_admin();
$__self = basename($_SERVER['SCRIPT_NAME'], '.php');
$__newOrders = $__me && can('orders.view') ? (int)db_val("SELECT COUNT(*) FROM orders WHERE status = 'pending'") : 0;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($admin_title ?? 'Admin') . ' · ' . setting('site_name', 'Ebaya') . ' Admin') ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if ($__me): ?>
<aside class="admin-sidebar offcanvas-lg offcanvas-start" id="adminNav" tabindex="-1">
  <div class="sidebar-brand"><a href="<?= e(admin_url()) ?>"><?= e(setting('site_name', 'Ebaya')) ?></a><span>Admin</span>
    <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#adminNav" aria-label="Close"></button></div>
  <nav class="sidebar-nav">
    <?php foreach (admin_menu() as $__group => $__entries):
      $__visible = array_filter($__entries, fn($i) => can($i[3]));
      if (!$__visible) continue; ?>
      <div class="nav-group"><?= e($__group) ?></div>
      <?php foreach ($__visible as [$__label, $__file, $__icon]): ?>
        <a href="<?= e(admin_url($__file === 'index' ? '' : $__file)) ?>" class="<?= $__self === $__file || ($__file === 'products' && $__self === 'product-edit') || ($__file === 'orders' && $__self === 'order-view') || ($__file === 'customers' && $__self === 'customer-view') ? 'active' : '' ?>">
          <i class="bi bi-<?= e($__icon) ?>"></i><span><?= e($__label) ?></span>
          <?php if ($__file === 'orders' && $__newOrders): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= $__newOrders ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminNav" aria-label="Menu"><i class="bi bi-list"></i></button>
    <h1 class="admin-title"><?= e($admin_title ?? 'Admin') ?></h1>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(url()) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> <span class="d-none d-md-inline">View store</span></a>
      <div class="dropdown">
        <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-md-inline"><?= e($__me['name']) ?></span></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted"><?= e($__me['role_name']) ?></span></li>
          <li><a class="dropdown-item" href="<?= e(admin_url('profile')) ?>">My profile</a></li>
          <li><form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="dropdown-item">Sign out</button></form></li>
        </ul>
      </div>
    </div>
  </header>
  <div class="admin-content">
<?php endif; ?>
<?php foreach (flashes() as $__fl): ?>
  <div class="alert alert-<?= e($__fl['type']) ?> alert-dismissible fade show" role="alert"><?= e($__fl['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
