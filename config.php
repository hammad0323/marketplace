<?php
/**
 * config.php — bootstrap: DB connection ($conn), session, constants.
 * Every other script starts with: require __DIR__ . '/config.php';
 * (or require __DIR__ . '/../config.php'; from /admin or /ajax)
 */

// Refuse direct access even if .htaccess isn't honored by the host.
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak raw errors to visitors
date_default_timezone_set('Asia/Karachi');

// ---------------------------------------------------------------------
// Database credentials — the only section you edit for deployment.
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'weddinghall_test');
define('DB_USER', 'whtest');
define('DB_PASS', 'whtest_pass');

// The public website + demo data seed as business id 1. On a true
// multi-tenant deployment a super_admin can add more businesses from
// /admin/businesses.php; the public site here always serves business 1.
define('DEFAULT_BUSINESS_ID', 1);

// ---------------------------------------------------------------------
// Base URL (auto-detected; override if your host needs it hard-coded)
// ---------------------------------------------------------------------
$wh_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https://' : 'http://';
$wh_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $wh_scheme . $wh_host);
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024); // 3MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ---------------------------------------------------------------------
// Secure session
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $wh_scheme === 'https://',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('whsid');
    session_start();
}

// Session idle timeout (30 min) for admin sessions.
if (!empty($_SESSION['admin_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// ---------------------------------------------------------------------
// Database connection
// ---------------------------------------------------------------------
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    http_response_code(500);
    $isCli = php_sapi_name() === 'cli';
    if (!$isCli) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Setup required</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;color:#333;line-height:1.6}'
            . 'code{background:#f4f4f4;padding:2px 6px;border-radius:4px}</style></head><body>'
            . '<h1>Database connection failed</h1>'
            . '<p>The application could not connect to MySQL with the credentials in <code>config.php</code>.</p>'
            . '<p>Check that: the database exists, <code>DB_HOST</code>/<code>DB_NAME</code>/<code>DB_USER</code>/<code>DB_PASS</code> are correct, '
            . 'and that <code>database.sql</code> has been imported. See <code>README.md</code> for step-by-step install instructions.</p>'
            . '</body></html>';
    }
    error_log('DB connection failed: ' . mysqli_connect_error());
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';
