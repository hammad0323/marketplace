<?php
/**
 * FMCG QMS Platform - Core Configuration
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
if (!is_dir(__DIR__ . '/../logs')) {
    @mkdir(__DIR__ . '/../logs', 0755, true);
}

define('APP_ENV', getenv('QMS_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

define('DB_HOST', getenv('QMS_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('QMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('QMS_DB_PASS') ?: '');
define('DB_NAME', getenv('QMS_DB_NAME') ?: 'fmcg_qms');
define('DB_PORT', getenv('QMS_DB_PORT') ?: 3306);

define('BASE_PATH', dirname(__DIR__));

/**
 * Auto-detects the app's base URL path from the server environment, so the same codebase
 * works unmodified at the site root, in a subfolder (e.g. /beta), or nested any number of
 * levels deep - no config edit needed after upload. Compares the app's real filesystem path
 * against DOCUMENT_ROOT (reliable on virtually every host: Apache, Nginx, LiteSpeed).
 * QMS_BASE_URL env var, if set, always wins (for exotic setups where DOCUMENT_ROOT is wrong).
 */
function detect_base_url(): string
{
    $envOverride = getenv('QMS_BASE_URL');
    if ($envOverride !== false && $envOverride !== '') {
        return rtrim($envOverride, '/');
    }
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot === '') {
        return ''; // CLI (cron) or unknown docroot: fall back to relative/root paths
    }
    $appRoot = realpath(dirname(__DIR__)); // filesystem path of the app root (parent of includes/)
    $docRootReal = realpath($docRoot);
    if ($appRoot === false || $docRootReal === false) {
        return '';
    }
    $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
    $docRootReal = rtrim(str_replace('\\', '/', $docRootReal), '/');
    if (strpos($appRoot, $docRootReal) !== 0) {
        return ''; // app root isn't under the doc root (e.g. symlink oddities): safest fallback
    }
    return rtrim(substr($appRoot, strlen($docRootReal)), '/');
}

define('BASE_URL', detect_base_url());
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

define('APP_NAME_DEFAULT', 'QualityCore');
define('APP_TAGLINE', 'Intelligent Quality Management for FMCG Manufacturing');

define('PRIMARY_COLOR_DEFAULT', '#2563EB');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv']);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('CSRF_TOKEN_NAME', 'qms_csrf_token');

date_default_timezone_set('UTC');
