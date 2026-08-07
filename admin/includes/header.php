<?php
/** Admin dashboard shell. Call require_admin_page() before including this. */
$user = current_user();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$pendingDoctorCount = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='pending'"))['c'];
$pageTitle = ($pageTitle ?? 'Dashboard') . ' — Admin — ' . SITE_NAME;
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
<link rel="stylesheet" href="/assets/fonts/remixicon/remixicon.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-logged-in="1">
<div class="dash-shell">
    <aside class="dash-sidebar" id="dash-sidebar">
        <a href="/admin/dashboard.php" class="brand"><span class="brand-mark"><i class="ri-shield-star-fill"></i></span> <?= e(SITE_NAME) ?></a>
        <nav class="dash-nav">
            <a href="/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"><i class="ri-dashboard-3-line"></i> Dashboard</a>
            <div class="nav-section-title">People</div>
            <a href="/admin/doctors.php" class="<?= in_array($currentPage, ['doctors.php', 'doctor-view.php']) ? 'active' : '' ?>">
                <i class="ri-stethoscope-line"></i> Doctors
                <?php if ($pendingDoctorCount > 0): ?><span class="badge badge-pending" style="margin-left:auto;"><?= $pendingDoctorCount ?></span><?php endif; ?>
            </a>
            <a href="/admin/patients.php" class="<?= $currentPage === 'patients.php' ? 'active' : '' ?>"><i class="ri-group-line"></i> Patients</a>
            <div class="nav-section-title">Operations</div>
            <a href="/admin/appointments.php" class="<?= $currentPage === 'appointments.php' ? 'active' : '' ?>"><i class="ri-calendar-check-line"></i> Appointments</a>
            <a href="/admin/specializations.php" class="<?= $currentPage === 'specializations.php' ? 'active' : '' ?>"><i class="ri-price-tag-3-line"></i> Specializations</a>
            <a href="/admin/messages.php" class="<?= $currentPage === 'messages.php' ? 'active' : '' ?>"><i class="ri-mail-line"></i> Messages</a>
            <div class="nav-section-title">System</div>
            <a href="/admin/settings.php" class="<?= $currentPage === 'settings.php' ? 'active' : '' ?>"><i class="ri-settings-3-line"></i> Site Settings</a>
            <a href="/admin/logs.php" class="<?= $currentPage === 'logs.php' ? 'active' : '' ?>"><i class="ri-file-list-3-line"></i> Activity Logs</a>
            <div class="nav-section-title">Account</div>
            <a href="/logout.php"><i class="ri-logout-box-line"></i> Logout</a>
        </nav>
    </aside>
    <main class="dash-main">
        <div class="dash-topbar">
            <button class="btn-icon sidebar-toggle" data-sidebar-toggle><i class="ri-menu-3-line"></i></button>
            <h2 style="font-size:19px;"><?= e($heading ?? 'Dashboard') ?></h2>
            <div style="display:flex;align-items:center;gap:14px;">
                <button class="theme-toggle" data-theme-toggle><i class="ri-moon-line"></i></button>
                <div class="user-menu">
                    <button class="user-avatar-btn" data-dropdown-trigger="admin-user-dropdown">
                        <img src="<?= e(avatar_url($user['avatar'], $user['full_name'])) ?>" alt="">
                        <span style="font-size:14px;font-weight:600;"><?= e($user['full_name']) ?></span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="dropdown-menu" id="admin-user-dropdown">
                        <a href="/index.php" target="_blank"><i class="ri-external-link-line"></i> View Site</a>
                        <div class="dropdown-divider"></div>
                        <a href="/logout.php"><i class="ri-logout-box-line"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="dash-content">
