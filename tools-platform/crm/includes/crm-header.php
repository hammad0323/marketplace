<?php
/**
 * crm-header.php — chrome for every crm/*.php page (post-login). Every
 * page starts with:
 *   require __DIR__ . '/../includes/config.php';
 *   require_business();
 *   $crmPageTitle = '...';
 *   require __DIR__ . '/includes/crm-header.php';
 */
if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}
$business = tp_current_business();
$crmPageTitle = $crmPageTitle ?? 'Dashboard';
$crmActive = $crmActive ?? '';

$overdueFollowupCount = (int) (tp_query_one(
    "SELECT COUNT(*) c FROM crm_followups WHERE business_id = ? AND completed_at IS NULL AND due_at < NOW()",
    'i',
    [tp_current_business_id()]
)['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($crmPageTitle) ?> — Sales CRM — <?= e(tp_setting('site_name')) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= tp_asset('css/admin.css') ?>">
<link rel="stylesheet" href="<?= tp_asset('css/crm.css') ?>">
</head>
<body data-base-url="<?= e(TOOLS_PLATFORM_URL) ?>" data-csrf="<?= e(tp_csrf_token()) ?>">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="<?= tp_url('crm/dashboard.php') ?>" class="admin-brand"><i class="bi bi-grid-1x2-fill"></i> Sales CRM</a>
    <nav>
      <a href="<?= tp_url('crm/dashboard.php') ?>" class="<?= $crmActive === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="<?= tp_url('crm/leads.php') ?>" class="<?= $crmActive === 'leads' ? 'active' : '' ?>"><i class="bi bi-kanban"></i> Pipeline</a>
      <a href="<?= tp_url('crm/customers.php') ?>" class="<?= $crmActive === 'customers' ? 'active' : '' ?>"><i class="bi bi-people"></i> Customers</a>
      <a href="<?= tp_url('crm/followups.php') ?>" class="<?= $crmActive === 'followups' ? 'active' : '' ?>">
        <i class="bi bi-bell"></i> Follow-ups
        <?php if ($overdueFollowupCount > 0): ?><span class="badge rounded-pill bg-danger ms-1"><?= $overdueFollowupCount ?></span><?php endif; ?>
      </a>
      <a href="<?= tp_url('crm/documents.php') ?>" class="<?= $crmActive === 'documents' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Documents</a>
      <a href="<?= tp_url('crm/products.php') ?>" class="<?= $crmActive === 'products' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Products</a>
      <a href="<?= tp_url('crm/ledger.php') ?>" class="<?= $crmActive === 'ledger' ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> Payments / Khata</a>
      <a href="<?= tp_url('crm/visits.php') ?>" class="<?= $crmActive === 'visits' ? 'active' : '' ?>"><i class="bi bi-geo-alt"></i> Field Visits</a>
      <a href="<?= tp_url('crm/reports.php') ?>" class="<?= $crmActive === 'reports' ? 'active' : '' ?>"><i class="bi bi-bar-chart"></i> Reports</a>
      <a href="<?= tp_url('crm/targets.php') ?>" class="<?= $crmActive === 'targets' ? 'active' : '' ?>"><i class="bi bi-bullseye"></i> Targets</a>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <h1 class="h5 fw-bold m-0"><?= e($crmPageTitle) ?></h1>
      <div class="d-flex align-items-center gap-3">
        <a href="<?= tp_url() ?>" target="_blank" class="small">Tools Site <i class="bi bi-box-arrow-up-right"></i></a>
        <span class="small text-muted"><?= e($business['business_name'] ?? '') ?></span>
        <a href="<?= tp_url('crm/logout.php') ?>" class="btn btn-sm btn-outline-secondary">Logout</a>
      </div>
    </header>
    <main class="admin-content">
      <?php foreach (tp_flash_get() as $flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
