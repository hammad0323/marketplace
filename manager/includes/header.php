<?php
/** Manager dashboard shell — deliberately minimal, since a manager only ever does one thing: run a doctor's ticket queue. */
$user = current_user();
$doctorId = current_manager_doctor_id();
if (!$doctorId) {
    http_response_code(403);
    exit('Your manager account is not linked to a doctor. Please contact the doctor who added you.');
}
$doctorName = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT u.full_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ' . (int) $doctorId))['full_name'] ?? '';
$pageTitle = ($pageTitle ?? 'Queue') . ' — ' . SITE_NAME;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= favicon_tag_html() ?>
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
        <a href="/" class="brand"><?= brand_logo_html('ri-heart-pulse-fill') ?></a>
        <nav class="dash-nav">
            <a href="/manager/queue" class="active"><i class="ri-ticket-2-line"></i> Ticket Queue</a>
            <div class="nav-section-title">Account</div>
            <a href="/logout"><i class="ri-logout-box-line"></i> Logout</a>
        </nav>
    </aside>
    <main class="dash-main">
        <div class="dash-topbar">
            <button class="btn-icon sidebar-toggle" data-sidebar-toggle><i class="ri-menu-3-line"></i></button>
            <h2 style="font-size:19px;"><?= e($heading ?? 'Ticket Queue') ?> <span style="font-weight:400;color:var(--color-text-muted);font-size:15px;">— <?= e($doctorName) ?></span></h2>
            <div style="display:flex;align-items:center;gap:14px;">
                <span style="font-size:14px;font-weight:600;"><?= e($user['full_name']) ?></span>
                <a href="/logout" class="btn-icon"><i class="ri-logout-box-line"></i></a>
            </div>
        </div>
        <div class="dash-content">
