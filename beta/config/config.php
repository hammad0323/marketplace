<?php
/**
 * Bootstrap file. Every page includes this ONE file first.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // production-safe; flip to '1' while developing locally

require __DIR__ . '/constants.php';
require __DIR__ . '/database.php';
require __DIR__ . '/functions.php';

if (get_setting('maintenance_mode', '0') === '1' && !current_admin()) {
    $script = basename($_SERVER['SCRIPT_NAME']);
    $allowed = ['login.php', 'logout.php'];
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false && !in_array($script, $allowed, true)) {
        http_response_code(503);
        die('<div style="font-family:sans-serif;max-width:520px;margin:100px auto;text-align:center;">
             <h1>' . clean(site_name()) . '</h1><h2>We\'ll be back soon</h2>
             <p>The site is currently undergoing scheduled maintenance.</p></div>');
    }
}

check_commission_overdue();
