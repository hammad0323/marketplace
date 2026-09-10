<?php
/** Patient dashboard shell. Call require_patient_page() before including this. */
$user = current_user();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$unreadCount = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT COUNT(*) c FROM notifications WHERE user_id = ' . (int) $user['id'] . ' AND is_read = 0'))['c'];
$pageTitle = ($pageTitle ?? 'Dashboard') . ' — ' . SITE_NAME;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap"></noscript>
<link rel="preload" as="font" type="font/woff2" href="/assets/fonts/remixicon/remixicon.woff2?t=1708865856766" crossorigin>
<link rel="preload" as="style" href="<?= asset_url('/assets/fonts/remixicon/remixicon.css') ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= asset_url('/assets/fonts/remixicon/remixicon.css') ?>"></noscript>
<link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
</head>
<body data-logged-in="1">
<div class="dash-shell">
    <aside class="dash-sidebar" id="dash-sidebar">
        <a href="/" class="brand"><span class="brand-mark"><i class="ri-heart-pulse-fill"></i></span> <?= brand_wordmark_html() ?></a>
        <nav class="dash-nav">
            <a href="/patient/dashboard" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"><i class="ri-dashboard-3-line"></i> Dashboard</a>
            <a href="/patient/appointments" class="<?= $currentPage === 'appointments.php' ? 'active' : '' ?>"><i class="ri-calendar-check-line"></i> Appointments</a>
            <a href="/patient/messages" class="<?= $currentPage === 'messages.php' ? 'active' : '' ?>"><i class="ri-chat-3-line"></i> Messages</a>
            <a href="/patient/orders" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>"><i class="ri-shopping-bag-3-line"></i> My Orders</a>
            <a href="/doctors"><i class="ri-search-line"></i> Find Doctors</a>
            <a href="/patient/profile" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"><i class="ri-user-line"></i> Profile</a>
            <div class="nav-section-title">Account</div>
            <a href="/logout"><i class="ri-logout-box-line"></i> Logout</a>
        </nav>
    </aside>
    <main class="dash-main">
        <div class="dash-topbar">
            <button class="btn-icon sidebar-toggle" data-sidebar-toggle><i class="ri-menu-3-line"></i></button>
            <h2 style="font-size:19px;"><?= e($heading ?? 'Dashboard') ?></h2>
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="user-menu">
                    <button class="btn-icon" id="notif-bell-btn" data-dropdown-trigger="patient-dropdown" style="position:relative;">
                        <i class="ri-notification-3-line"></i>
                        <span id="notif-badge-dot" style="position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--color-danger);<?= $unreadCount > 0 ? '' : 'display:none;' ?>"></span>
                    </button>
                    <div class="dropdown-menu" id="patient-dropdown" style="min-width:280px;">
                        <div style="padding:8px 12px;font-weight:700;font-size:13px;">Notifications</div>
                        <div id="notif-list">
                        <?php
                        $notifs = mysqli_query(db(), 'SELECT * FROM notifications WHERE user_id = ' . (int) $user['id'] . ' ORDER BY created_at DESC LIMIT 6');
                        if (mysqli_num_rows($notifs) === 0): ?>
                        <div style="padding:12px;font-size:13px;color:var(--color-text-muted);">No notifications yet.</div>
                        <?php else: while ($n = mysqli_fetch_assoc($notifs)): ?>
                        <a href="<?= e($n['link'] ?: '#') ?>" style="display:block;padding:10px 12px;white-space:normal;">
                            <strong style="display:block;font-size:13px;"><?= e($n['title']) ?></strong>
                            <span style="font-size:12px;color:var(--color-text-muted);"><?= e($n['message']) ?></span>
                        </a>
                        <?php endwhile; endif; ?>
                        </div>
                    </div>
                </div>
                <div class="user-menu">
                    <button class="user-avatar-btn" data-dropdown-trigger="patient-user-dropdown">
                        <img src="<?= e(avatar_url($user['avatar'], $user['full_name'])) ?>" alt="">
                        <span style="font-size:14px;font-weight:600;"><?= e(explode(' ', $user['full_name'])[0]) ?></span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="dropdown-menu" id="patient-user-dropdown">
                        <a href="/patient/profile"><i class="ri-user-line"></i> Profile</a>
                        <div class="dropdown-divider"></div>
                        <a href="/logout"><i class="ri-logout-box-line"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="dash-content">
