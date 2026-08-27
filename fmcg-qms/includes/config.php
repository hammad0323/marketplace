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
define('BASE_URL', rtrim(getenv('QMS_BASE_URL') ?: '/fmcg-qms', '/'));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

define('APP_NAME', 'QualityCore');
define('APP_TAGLINE', 'Intelligent Quality Management for FMCG Manufacturing');

define('PRIMARY_COLOR', '#2563EB');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv']);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('CSRF_TOKEN_NAME', 'qms_csrf_token');

date_default_timezone_set('UTC');
