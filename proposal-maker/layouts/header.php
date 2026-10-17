<?php
/**
 * Admin layout header. Expects $pageTitle and optionally $activeNav, $bodyClass, $extraHead.
 */
$user = current_user();
$co = company();
$activeNav = $activeNav ?? '';
$nav = [
    ['index.php', 'dashboard', 'bi-grid-1x2', 'Dashboard'],
    ['proposals.php', 'proposals', 'bi-file-earmark-richtext', 'Proposals'],
    ['proposal-create.php', 'create', 'bi-plus-circle', 'Create Proposal'],
    ['templates.php', 'templates', 'bi-palette2', 'Design Templates'],
    ['service-templates.php', 'services', 'bi-collection', 'Pre-built Proposals'],
    ['library.php', 'library', 'bi-bookmark-star', 'Content Library'],
];
$navSettings = [
    ['settings.php', 'settings', 'bi-building', 'Company Profile'],
    ['settings.php?tab=brand', 'brand', 'bi-droplet-half', 'Brand & Colors'],
    ['settings.php?tab=email', 'email', 'bi-envelope-at', 'Email / SMTP'],
];
if (($user['role'] ?? '') === 'admin') $navSettings[] = ['users.php', 'users', 'bi-people', 'Users'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($pageTitle ?? 'Dashboard') . ' — ' . ($co['company_name'] ?? 'Proposal Maker')) ?></title>

<link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/sweetalert2/sweetalert2.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
<?= $extraHead ?? '' ?>
<script>window.PM = {base: <?= json_encode(APP_URL) ?>, csrf: <?= json_encode(csrf_token()) ?>};</script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (empty($noLayout)): ?>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a href="<?= url('index.php') ?>" class="brand">
      <span class="brand-mark"><i class="bi bi-file-earmark-richtext"></i></span>
      <span class="brand-text">Proposal<b>Maker</b></span>
    </a>
    <nav class="side-nav">
      <div class="nav-label">Workspace</div>
      <?php foreach ($nav as [$href, $key, $icon, $label]): ?>
        <a href="<?= url($href) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i><span><?= $label ?></span></a>
      <?php endforeach; ?>
      <div class="nav-label">Settings</div>
      <?php foreach ($navSettings as [$href, $key, $icon, $label]): ?>
        <a href="<?= url($href) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i><span><?= $label ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('proposal-create.php') ?>" class="btn btn-brand w-100"><i class="bi bi-lightning-charge"></i> New Proposal</a>
    </div>
  </aside>
  <div class="sidebar-backdrop" onclick="document.body.classList.remove('side-open')"></div>
  <main class="main">
    <header class="topbar">
      <button class="btn btn-icon d-lg-none" onclick="document.body.classList.toggle('side-open')"><i class="bi bi-list"></i></button>
      <div class="topbar-title"><?= e($pageTitle ?? '') ?></div>
      <form class="topbar-search d-none d-md-flex" action="<?= url('proposals.php') ?>">
        <i class="bi bi-search"></i><input name="q" placeholder="Search proposals, clients…" value="<?= e($_GET['q'] ?? '') ?>">
      </form>
      <div class="dropdown ms-2">
        <button class="btn btn-user dropdown-toggle" data-bs-toggle="dropdown">
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span>
          <span class="d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
          <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
          <li><a class="dropdown-item" href="<?= url('settings.php') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
        </ul>
      </div>
    </header>
    <div class="content">
      <?php foreach (take_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
      <?php endforeach; ?>
<?php endif; ?>
