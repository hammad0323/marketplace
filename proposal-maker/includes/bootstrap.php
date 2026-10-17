<?php
/**
 * START-UP FILE — every page includes this first.
 *
 * It does 4 things:
 *   1. loads config.php (database login). If it is missing, it sends you to install.php
 *   2. works out the website address (APP_URL) so links and images work
 *   3. starts the PHP session (used for login)
 *   4. loads all the other files in this folder
 */
define('PM_APP', true);
define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

if (!file_exists(APP_ROOT . '/config.php')) {
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($self !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    require APP_ROOT . '/config.php';
}

if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

if (!defined('BASE_URL') || BASE_URL === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: ''), '/') : '';
    $appRoot = str_replace('\\', '/', APP_ROOT);
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $path = substr($appRoot, strlen($docRoot));
    } else {
        // Fallback: derive from the script path (scripts live in root, ajax/ or proposal/)
        $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path = preg_replace('#/(ajax)$#', '', $path);
    }
    define('APP_URL', ($https ? 'https' : 'http') . '://' . $host . rtrim($path, '/'));
} else {
    define('APP_URL', rtrim(BASE_URL, '/'));
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('PMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/proposal_model.php';
