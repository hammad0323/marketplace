<?php
/**
 * Shared app-shell layout (topbar + sidebar) for admin/manager/employee pages.
 * Expects $pageTitle and $activeMenu to be set by the including page.
 */
$pageTitle = $pageTitle ?? app_name();
$activeMenu = $activeMenu ?? '';
$role = current_role();
$unread = is_logged_in() ? get_unread_count(current_user_id()) : 0;

$menus = [
    'super_admin' => [
        ['label' => 'Overview', 'items' => [
            ['dashboard', 'Dashboard', 'bi-speedometer2', 'admin/dashboard.php'],
        ]],
        ['label' => 'Companies', 'items' => [
            ['companies', 'Companies', 'bi-buildings', 'admin/companies.php'],
            ['plans', 'Subscription Plans', 'bi-credit-card', 'admin/subscription-plans.php'],
        ]],
        ['label' => 'Quality Tool Engine', 'items' => [
            ['tool-categories', 'Tool Categories', 'bi-tags', 'admin/tool-categories.php'],
            ['tools', 'Dynamic Tool Builder', 'bi-tools', 'admin/tools.php'],
        ]],
        ['label' => 'Platform', 'items' => [
            ['ai-settings', 'AI Settings', 'bi-robot', 'admin/ai-settings.php'],
            ['email-settings', 'Email Templates', 'bi-envelope', 'admin/email-settings.php'],
            ['platform-settings', 'Platform Settings', 'bi-gear', 'admin/platform-settings.php'],
        ]],
        ['label' => 'Monitoring', 'items' => [
            ['system-logs', 'System Logs', 'bi-terminal', 'admin/system-logs.php'],
            ['login-activity', 'Login Activity', 'bi-shield-lock', 'admin/login-activity.php'],
            ['ai-usage', 'AI Usage', 'bi-cpu', 'admin/ai-usage.php'],
        ]],
    ],
    'manager' => [
        ['label' => 'Overview', 'items' => [
            ['dashboard', 'Dashboard', 'bi-speedometer2', 'manager/dashboard.php'],
        ]],
        ['label' => 'Quality Operations', 'items' => [
            ['tools', 'Quality Tools', 'bi-clipboard-check', 'manager/quality-tools.php'],
            ['submissions', 'Inspections / Submissions', 'bi-list-check', 'manager/submissions.php'],
            ['spc', 'SPC', 'bi-graph-up', 'manager/spc.php'],
            ['issues', 'Quality Issues', 'bi-exclamation-triangle', 'manager/issues.php'],
            ['ncr', 'NCR', 'bi-file-earmark-excel', 'manager/ncr.php'],
            ['capa', 'CAPA', 'bi-clipboard-data', 'manager/capa.php'],
            ['fmea', 'FMEA', 'bi-diagram-3', 'manager/fmea.php'],
            ['haccp', 'HACCP', 'bi-shield-check', 'manager/haccp.php'],
            ['audits', 'Audits', 'bi-clipboard2-check', 'manager/audits.php'],
        ]],
        ['label' => 'Production', 'items' => [
            ['products', 'Products', 'bi-box-seam', 'manager/products.php'],
            ['batches', 'Batches', 'bi-upc-scan', 'manager/batches.php'],
            ['traceability', 'Traceability', 'bi-diagram-2', 'manager/traceability.php'],
            ['oee', 'OEE', 'bi-speedometer', 'manager/oee.php'],
            ['lean', 'Lean & Continuous Improvement', 'bi-lightning-charge', 'manager/lean.php'],
        ]],
        ['label' => 'Quality Chain', 'items' => [
            ['suppliers', 'Suppliers', 'bi-truck', 'manager/suppliers.php'],
            ['complaints', 'Complaints', 'bi-chat-dots', 'manager/complaints.php'],
        ]],
        ['label' => 'Insights', 'items' => [
            ['reports', 'Reports', 'bi-file-earmark-bar-graph', 'manager/reports.php'],
            ['ai-assistant', 'AI Assistant', 'bi-robot', 'manager/ai-assistant.php'],
        ]],
        ['label' => 'Organization', 'items' => [
            ['employees', 'Employees', 'bi-people', 'manager/employees.php'],
            ['departments', 'Departments', 'bi-diagram-3-fill', 'manager/departments.php'],
            ['settings', 'Settings', 'bi-gear', 'manager/settings.php'],
        ]],
    ],
    'employee' => [
        ['label' => 'My Work', 'items' => [
            ['dashboard', 'Dashboard', 'bi-speedometer2', 'employee/dashboard.php'],
            ['floor-mode', 'Floor Mode', 'bi-tablet', 'employee/floor-mode.php'],
            ['my-issues', 'My Issues', 'bi-exclamation-triangle', 'employee/my-issues.php'],
            ['my-actions', 'My Actions', 'bi-check2-square', 'employee/my-actions.php'],
        ]],
    ],
];
$menu = $menus[$role] ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-base-url="<?= out(BASE_URL) ?>" data-csrf="<?= out(csrf_token()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= out($pageTitle) ?> - <?= out(app_name()) ?></title>
<?php $favicon = app_favicon_url(); ?>
<?php if ($favicon): ?><link rel="icon" href="<?= out($favicon) ?>">
<?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><rect width=24 height=24 rx=6 fill=%22%232563EB%22/><path d=%22M7 12.5l3 3 7-7%22 stroke=%22white%22 stroke-width=%222.4%22 fill=%22none%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/></svg>">
<?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>?v=2" rel="stylesheet">
<?php $__primary = app_primary_color(); if ($__primary !== PRIMARY_COLOR_DEFAULT): ?>
<style>:root{--primary:<?= out($__primary) ?>;--primary-dark:<?= out($__primary) ?>;}</style>
<?php endif; ?>
</head>
<body>
<div class="app-shell">
  <aside class="app-sidebar" id="appSidebar">
    <div class="brand">
      <?php $logo = app_logo_url(); ?>
      <?php if ($logo): ?><img src="<?= out($logo) ?>" alt="<?= out(app_name()) ?>" style="height:32px;width:auto;border-radius:6px;">
      <?php else: ?><span class="brand-badge">Q</span><?php endif; ?>
      <?= out(app_name()) ?>
    </div>
    <?php foreach ($menu as $section): ?>
      <div class="nav-section-label"><?= out($section['label']) ?></div>
      <?php foreach ($section['items'] as [$key, $label, $icon, $href]): ?>
        <a class="nav-link <?= $activeMenu === $key ? 'active' : '' ?>" href="<?= base_url($href) ?>">
          <i class="bi <?= out($icon) ?>"></i><?= out($label) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="p-3 mt-2">
      <a href="<?= base_url('logout.php') ?>" class="nav-link text-danger-emphasis"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </div>
  </aside>

  <div class="app-main">
    <header class="app-topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <?php if (in_array($role, ['manager', 'employee'], true)): ?>
        <div class="search-box position-relative">
          <i class="bi bi-search"></i>
          <input type="text" id="globalSearchInput" class="form-control" placeholder="Search employees, batches, issues...">
          <div id="globalSearchResults" class="dropdown-menu d-none" style="display:block; width:100%; max-height:360px; overflow:auto;"></div>
        </div>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if ($role === 'manager'): ?>
        <a href="<?= base_url('manager/ai-assistant.php') ?>" class="topbar-icon-btn" title="AI Assistant"><i class="bi bi-robot"></i></a>
        <?php endif; ?>
        <?php if (in_array($role, ['manager', 'employee'], true)): ?>
        <div class="dropdown">
          <button class="topbar-icon-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i>
            <span class="notif-dot" id="notifCount" style="display:none;">0</span>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px;">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
              <strong class="small">Notifications</strong>
              <a href="#" id="markAllRead" class="small">Mark all read</a>
            </div>
            <div id="notifList" style="max-height:360px; overflow:auto;"></div>
          </div>
        </div>
        <?php endif; ?>
        <div class="dropdown">
          <button class="d-flex align-items-center gap-2 btn btn-light border" data-bs-toggle="dropdown">
            <span class="d-none d-sm-inline small fw-semibold"><?= out(current_user_name()) ?></span>
            <i class="bi bi-person-circle fs-5"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted text-capitalize"><?= out(str_replace('_',' ',$role)) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="app-content">
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="alert alert-<?= out($flash['type']) ?> alert-dismissible fade show" role="alert">
          <?= out($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
