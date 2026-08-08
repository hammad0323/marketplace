<?php
/**
 * Global bootstrap. Every page on the site includes this ONE file first —
 * it starts the session, opens the mysqli connection, and loads the shared
 * helper libraries used everywhere else.
 *
 * TO DEPLOY: edit the DB_* constants (or set the matching environment
 * variables) and import database/schema.sql then database/seed.sql.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

define('APP_DEBUG', getenv('APP_DEBUG') === '1');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'mediconnect');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));

define('SITE_NAME', 'MediConnect');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('UPLOAD_URL', '/uploads');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
define('APP_URL', ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

// ---------------------------------------------------------------------------
// Session — locked-down cookie params, must run before session_start().
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Absolute session idle timeout (30 minutes) — preserves the "redirect back
// to what I was doing" target so the user doesn't lose their place.
define('SESSION_IDLE_TIMEOUT', 1800);
if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
    $_SESSION = array_intersect_key($_SESSION, ['intended_url' => true]);
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

// ---------------------------------------------------------------------------
// Database — single shared mysqli connection, prepared statements only.
// ---------------------------------------------------------------------------
mysqli_report(MYSQLI_REPORT_OFF);
$GLOBALS['db'] = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$GLOBALS['db']) {
    error_log('DB connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    $message = APP_DEBUG
        ? htmlspecialchars(mysqli_connect_error())
        : 'The site is temporarily unavailable. Please try again shortly.';
    exit('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;'
        . 'border:1px solid #f3c2c5;background:#fcebec;border-radius:12px;color:#8a161d;">'
        . '<h2 style="margin-top:0;">Database connection failed</h2><p>' . $message . '</p></div>');
}
mysqli_set_charset($GLOBALS['db'], 'utf8mb4');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/auth.php';

// Drives doctor chat "online" status — cheap indexed UPDATE, safe to run every request.
if (!empty($_SESSION['user_id'])) {
    touch_last_active((int) $_SESSION['user_id']);
}
