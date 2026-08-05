<?php
/**
 * Global bootstrap. Every page on the site requires this ONE file at
 * the top — it loads constants, starts the session, connects to the
 * database, and loads every helper/middleware/data function used
 * everywhere else (see includes/).
 *
 * ============================================================
 *  TO DEPLOY: edit the four DB_* constants in config/database.php to
 *  match your database, then import database.sql. Nothing else in
 *  this project needs to be edited or moved.
 * ============================================================
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

// Every file required below this point trusts MP_BOOTSTRAP instead of
// re-checking SCRIPT_FILENAME, since several small, generically-named
// helper/function files (e.g. includes/functions/vendors.php) would
// otherwise collide with page files of the same basename elsewhere in
// the project (e.g. admin/vendors.php) and block themselves.
define('MP_BOOTSTRAP', true);

require __DIR__ . '/constants.php';

if (ERROR_REPORTING_ENABLED) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

require __DIR__ . '/session.php';
require __DIR__ . '/database.php';
require __DIR__ . '/routes.php';

foreach (glob(__DIR__ . '/../includes/helpers/*.php') as $helperFile) {
    require $helperFile;
}
foreach (glob(__DIR__ . '/../includes/middlewares/*.php') as $middlewareFile) {
    require $middlewareFile;
}
foreach (glob(__DIR__ . '/../includes/functions/*.php') as $functionFile) {
    require $functionFile;
}
