<?php
/**
 * Fahim Store - core configuration.
 *
 * EDIT THIS FILE (or create includes/config.local.php, which overrides it
 * and is ignored by git) with your real database and API credentials.
 * Credentials are read from here only - never hardcode them elsewhere.
 */
if (!defined('FS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

// Optional local override file (recommended on servers).
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

function fs_env(string $key, $default = null)
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// ---------------------------------------------------------------
// Database (MySQLi)
// ---------------------------------------------------------------
defined('DB_HOST') || define('DB_HOST', fs_env('FS_DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', (int) fs_env('FS_DB_PORT', 3306));
defined('DB_NAME') || define('DB_NAME', fs_env('FS_DB_NAME', 'fahim_store'));
defined('DB_USER') || define('DB_USER', fs_env('FS_DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', fs_env('FS_DB_PASS', ''));

// ---------------------------------------------------------------
// Application
// ---------------------------------------------------------------
// Leave APP_URL empty to auto-detect (works in a sub-folder too).
// On production set e.g. 'https://www.fahimstore.pk'
defined('APP_URL')   || define('APP_URL', rtrim((string) fs_env('FS_APP_URL', ''), '/'));
defined('APP_ENV')   || define('APP_ENV', fs_env('FS_APP_ENV', 'production')); // production | development
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Karachi');
defined('APP_SECRET') || define('APP_SECRET', fs_env('FS_APP_SECRET', 'change-this-to-a-long-random-string'));
defined('USE_MIN_ASSETS') || define('USE_MIN_ASSETS', APP_ENV === 'production');

// Upload limits
defined('MAX_UPLOAD_MB') || define('MAX_UPLOAD_MB', 5);

// ---------------------------------------------------------------
// Email (SMTP). Leave SMTP_HOST empty to use PHP mail().
// ---------------------------------------------------------------
defined('SMTP_HOST')   || define('SMTP_HOST', fs_env('FS_SMTP_HOST', ''));
defined('SMTP_PORT')   || define('SMTP_PORT', (int) fs_env('FS_SMTP_PORT', 587));
defined('SMTP_SECURE') || define('SMTP_SECURE', fs_env('FS_SMTP_SECURE', 'tls')); // tls | ssl | none
defined('SMTP_USER')   || define('SMTP_USER', fs_env('FS_SMTP_USER', ''));
defined('SMTP_PASS')   || define('SMTP_PASS', fs_env('FS_SMTP_PASS', ''));
defined('MAIL_FROM')   || define('MAIL_FROM', fs_env('FS_MAIL_FROM', 'no-reply@fahimstore.pk'));
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'Fahim Store');

// ---------------------------------------------------------------
// WhatsApp Cloud API (Meta). Leave token empty to disable sending;
// admin still gets one-click "Chat on WhatsApp" links.
// ---------------------------------------------------------------
defined('WHATSAPP_API_URL')      || define('WHATSAPP_API_URL', fs_env('FS_WA_API_URL', 'https://graph.facebook.com/v19.0'));
defined('WHATSAPP_PHONE_ID')     || define('WHATSAPP_PHONE_ID', fs_env('FS_WA_PHONE_ID', ''));
defined('WHATSAPP_TOKEN')        || define('WHATSAPP_TOKEN', fs_env('FS_WA_TOKEN', ''));

date_default_timezone_set(APP_TIMEZONE);

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/php-error.log');
}
