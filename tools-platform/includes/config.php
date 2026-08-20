<?php
/**
 * config.php — THE file you edit to deploy this project.
 * Every public page and every admin page starts with:
 *   require __DIR__ . '/includes/config.php';
 * (or a relative path to it). This file refuses to be requested
 * directly, connects to MySQLi, starts the session, and loads the
 * rest of the engine (functions, auth, security, seo).
 */

if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    // no-op; kept so this file never has an "unused" static analyzer flag
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

// ---- Database credentials — EDIT THESE FOUR LINES -----------------
define('DB_HOST', getenv('TOOLS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('TOOLS_DB_NAME') ?: 'tools_platform');
define('DB_USER', getenv('TOOLS_DB_USER') ?: 'root');
define('DB_PASS', getenv('TOOLS_DB_PASS') ?: '');
// ---------------------------------------------------------------------

define('TOOLS_PLATFORM_ROOT', dirname(__DIR__));
define('TOOLS_PLATFORM_URL', rtrim(getenv('TOOLS_PLATFORM_URL') ?: '', '/'));
define('TOOLS_ENV', getenv('TOOLS_ENV') ?: 'production');

error_reporting(E_ALL);
ini_set('display_errors', TOOLS_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', TOOLS_PLATFORM_ROOT . '/logs/php-error.log');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    session_set_cookie_params(['samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/auth.php';

tp_load_settings();
