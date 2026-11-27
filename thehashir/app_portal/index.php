<?php
/**
 * Mosque & organization portal router:  /portal/{page}
 * A portal session only ever gives access to its own venue (current_venue_id()).
 */
require_once __DIR__ . '/../includes/backoffice.php';
require_once __DIR__ . '/../includes/venue_admin.php';
require_once __DIR__ . '/../includes/prayer_admin.php';

header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store');

$page = isset($BO_SEG[0]) && $BO_SEG[0] !== '' ? $BO_SEG[0] : 'dashboard';
$pages = array('login', 'logout', 'forgot', 'reset', 'dashboard', 'profile', 'details', 'detail-edit', 'announcements', 'prayer', 'jummah', 'account');
if (!in_array($page, $pages, true) || count($BO_SEG) > 2) {
    http_response_code(404);
    bo_start('venue', 'Page not found', array('bare' => !current_venue()));
    echo '<p>This page does not exist. <a href="' . e(url(PORTAL_PATH)) . '">Go to your dashboard</a></p>';
    bo_end();
    exit;
}
define('PORTAL_SELF', url(PORTAL_PATH . '/' . $page));
require __DIR__ . '/pages/' . $page . '.php';
