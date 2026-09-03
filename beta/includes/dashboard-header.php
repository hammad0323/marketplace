<?php
/**
 * Shared dashboard chrome for admin / shop / employee / customer panels.
 * Set $dashRole ('admin'|'shop'|'employee'|'customer'), $pageTitle and
 * $dashUserName/$dashLogoutUrl/$dashNotifType/$dashNotifId before requiring this.
 */
$sidebarLinks = dashboard_sidebar($dashRole);
$notifCount = isset($dashNotifType) ? unread_notification_count($dashNotifType, $dashNotifId) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($pageTitle ?? 'Dashboard') ?> | <?= clean(site_name()) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<style>:root{--primary-color:<?= clean(get_setting('primary_color','#2f6fed')) ?>;--secondary-color:<?= clean(get_setting('secondary_color','#0b1f3a')) ?>;--accent-color:<?= clean(get_setting('accent_color','#ff7a1a')) ?>;}</style>
</head>
<body class="dashboard-body">
<div class="dash-wrapper">
  <aside class="dash-sidebar">
    <div class="dash-logo"><a href="<?= base_url() ?>"><?= clean(site_name()) ?></a></div>
    <nav class="dash-nav">
      <?php foreach ($sidebarLinks as $link):
          [$label, $icon, $url] = $link;
          $perm = $link[3] ?? null;
          if ($perm && !staff_has_permission($perm)) continue;
          $active = (basename($_SERVER['SCRIPT_NAME']) === basename(parse_url($url, PHP_URL_PATH))) ? ' active' : '';
      ?>
        <a class="dash-nav-link<?= $active ?>" href="<?= $url ?>"><i class="fa-solid <?= $icon ?>"></i> <span><?= clean($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="dash-content">
    <header class="dash-topbar">
      <button class="dash-sidebar-toggle" id="dash-sidebar-toggle"><i class="fa-solid fa-bars"></i></button>
      <div class="dash-topbar-right">
        <a href="<?= base_url() ?>" target="_blank" class="dash-topbar-link"><i class="fa-solid fa-globe"></i> View Site</a>
        <span class="dash-notif"><i class="fa-solid fa-bell"></i> <?php if ($notifCount): ?><span class="dash-notif-badge"><?= $notifCount ?></span><?php endif; ?></span>
        <span class="dash-user"><i class="fa-solid fa-circle-user"></i> <?= clean($dashUserName ?? '') ?></span>
        <a href="<?= $dashLogoutUrl ?? '#' ?>" class="btn btn-sm btn-outline">Logout</a>
      </div>
    </header>
    <main class="dash-main">
      <?php require __DIR__ . '/alerts.php'; ?>
