<?php
/**
 * admin-header.php — chrome for every admin/*.php page. Every admin
 * page starts with:
 *   require __DIR__ . '/../includes/config.php';
 *   require_admin();
 *   $adminPageTitle = '...';
 *   require __DIR__ . '/includes/admin-header.php';
 */
if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}
$admin = tp_current_admin();
$adminPageTitle = $adminPageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminPageTitle) ?> — Admin — <?= e(tp_setting('site_name')) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= tp_asset('css/admin.css') ?>">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="<?= tp_url('admin/index.php') ?>" class="admin-brand"><i class="bi bi-grid-1x2-fill"></i> <?= e(tp_setting('site_name')) ?></a>
    <nav>
      <a href="<?= tp_url('admin/index.php') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="<?= tp_url('admin/categories.php') ?>"><i class="bi bi-folder2"></i> Categories</a>
      <a href="<?= tp_url('admin/tools.php') ?>"><i class="bi bi-tools"></i> Tools</a>
      <a href="<?= tp_url('admin/pages.php') ?>"><i class="bi bi-file-earmark-text"></i> Pages</a>
      <a href="<?= tp_url('admin/blog.php') ?>"><i class="bi bi-journal-text"></i> Blog</a>
      <a href="<?= tp_url('admin/media.php') ?>"><i class="bi bi-images"></i> Media</a>
      <a href="<?= tp_url('admin/redirects.php') ?>"><i class="bi bi-signpost-split"></i> Redirects</a>
      <a href="<?= tp_url('admin/settings.php') ?>"><i class="bi bi-gear"></i> Settings</a>
      <a href="<?= tp_url('admin/activity-log.php') ?>"><i class="bi bi-clock-history"></i> Activity Log</a>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <h1 class="h5 fw-bold m-0"><?= e($adminPageTitle) ?></h1>
      <div class="d-flex align-items-center gap-3">
        <a href="<?= tp_url() ?>" target="_blank" class="small">View Site <i class="bi bi-box-arrow-up-right"></i></a>
        <span class="small text-muted"><?= e($admin['name'] ?? '') ?> (<?= e($admin['role'] ?? '') ?>)</span>
        <a href="<?= tp_url('admin/logout.php') ?>" class="btn btn-sm btn-outline-secondary">Logout</a>
      </div>
    </header>
    <main class="admin-content">
      <?php foreach (tp_flash_get() as $flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
