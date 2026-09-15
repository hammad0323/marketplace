<?php
require_once __DIR__ . '/../../includes/functions.php';
require_admin_login();
$__admin = current_admin();
$__page = basename($_SERVER['SCRIPT_NAME']);

function nav_active($page, $files) {
    return in_array($page, (array)$files, true) ? 'active' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <i class="bi bi-bag-heart"></i> <span><?= e(get_setting('store_name', 'Fashion Store')) ?></span>
    </div>
    <nav class="admin-nav">
      <a href="index.php" class="<?= nav_active($__page, ['index.php']) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>

      <div class="nav-group-label">Catalog</div>
      <a href="products.php" class="<?= nav_active($__page, ['products.php','product_form.php']) ?>"><i class="bi bi-hanger"></i> Products</a>
      <a href="categories.php" class="<?= nav_active($__page, ['categories.php']) ?>"><i class="bi bi-diagram-3"></i> Categories</a>
      <a href="brands.php" class="<?= nav_active($__page, ['brands.php']) ?>"><i class="bi bi-award"></i> Brands</a>
      <a href="attributes.php" class="<?= nav_active($__page, ['attributes.php']) ?>"><i class="bi bi-palette"></i> Attributes</a>

      <div class="nav-group-label">Orders</div>
      <a href="orders.php" class="<?= nav_active($__page, ['orders.php','order_view.php']) ?>"><i class="bi bi-receipt"></i> All Orders</a>

      <div class="nav-group-label">Customers</div>
      <a href="customers.php" class="<?= nav_active($__page, ['customers.php','customer_view.php']) ?>"><i class="bi bi-people"></i> Customers</a>

      <div class="nav-group-label">Marketing</div>
      <a href="coupons.php" class="<?= nav_active($__page, ['coupons.php']) ?>"><i class="bi bi-ticket-perforated"></i> Coupons</a>
      <a href="reviews.php" class="<?= nav_active($__page, ['reviews.php']) ?>"><i class="bi bi-star"></i> Reviews</a>
      <a href="newsletter.php" class="<?= nav_active($__page, ['newsletter.php']) ?>"><i class="bi bi-envelope"></i> Newsletter</a>

      <div class="nav-group-label">Website</div>
      <a href="homepage_settings.php" class="<?= nav_active($__page, ['homepage_settings.php']) ?>"><i class="bi bi-house-heart"></i> Homepage Selector</a>
      <a href="banners.php" class="<?= nav_active($__page, ['banners.php']) ?>"><i class="bi bi-images"></i> Banners</a>
      <a href="homepage_sections.php" class="<?= nav_active($__page, ['homepage_sections.php','homepage_section_items.php']) ?>"><i class="bi bi-layout-text-window"></i> Homepage Sections</a>
      <a href="pages.php" class="<?= nav_active($__page, ['pages.php']) ?>"><i class="bi bi-file-text"></i> Pages</a>
      <a href="blog.php" class="<?= nav_active($__page, ['blog.php','blog_form.php']) ?>"><i class="bi bi-journal-richtext"></i> Blog</a>
      <a href="social_links.php" class="<?= nav_active($__page, ['social_links.php']) ?>"><i class="bi bi-share"></i> Social Links</a>

      <div class="nav-group-label">Commerce Settings</div>
      <a href="payments.php" class="<?= nav_active($__page, ['payments.php']) ?>"><i class="bi bi-credit-card"></i> Payment Methods</a>
      <a href="shipping.php" class="<?= nav_active($__page, ['shipping.php']) ?>"><i class="bi bi-truck"></i> Shipping</a>

      <div class="nav-group-label">System</div>
      <a href="settings.php" class="<?= nav_active($__page, ['settings.php']) ?>"><i class="bi bi-gear"></i> Settings</a>
      <a href="admin_users.php" class="<?= nav_active($__page, ['admin_users.php']) ?>"><i class="bi bi-person-badge"></i> Admin Users</a>
    </nav>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <button class="admin-burger d-lg-none" id="adminBurger"><i class="bi bi-list"></i></button>
      <div class="ms-auto d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>/index.php" target="_blank" class="small text-muted"><i class="bi bi-box-arrow-up-right"></i> View site</a>
        <div class="dropdown">
          <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= e($__admin['name'] ?? 'Admin') ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($__admin['role_name'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="admin-content">
      <?php foreach (flash_get_all() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
