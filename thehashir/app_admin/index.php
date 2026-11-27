<?php
/**
 * Super Admin panel router:  /manage/{page}
 * Every page except login / forgot / reset requires an admin session (see require_admin()).
 */
require_once __DIR__ . '/../includes/backoffice.php';
require_once __DIR__ . '/../includes/venue_admin.php';
require_once __DIR__ . '/../includes/prayer_admin.php';

header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store');

$page = isset($BO_SEG[0]) && $BO_SEG[0] !== '' ? $BO_SEG[0] : 'dashboard';
$pages = array(
    'login', 'logout', 'forgot', 'reset', 'dashboard', 'approvals', 'venues', 'venue-edit', 'details', 'detail-edit',
    'announcements', 'locations', 'prayer', 'jummah', 'blogs', 'blog-edit', 'pages', 'page-edit', 'users', 'settings',
    'subscribers', 'logs', 'account', 'api',
);
if (!in_array($page, $pages, true) || count($BO_SEG) > 2) {
    http_response_code(404);
    bo_start('admin', 'Page not found', array('bare' => !current_admin()));
    echo '<p>This page does not exist. <a href="' . e(url(ADMIN_PATH)) . '">Go to the dashboard</a></p>';
    bo_end();
    exit;
}
if (!storage_writable() && !in_array($page, array('login', 'logout'), true)) {
    flash('warning', 'The storage folder (' . STORAGE_DIR . ') is not writable. Settings, blog layouts, pages and login protection need it — see README.');
}
define('ADMIN_SELF', url(ADMIN_PATH . '/' . $page));
require __DIR__ . '/pages/' . $page . '.php';
