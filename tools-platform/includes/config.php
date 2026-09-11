<?php
/**
 * config.php — THE file you edit to deploy this project.
 * Every public page and every admin page starts with:
 *   require __DIR__ . '/includes/config.php';
 * (or a relative path to it). This file refuses to be requested
 * directly, connects to MySQLi, starts the session, and loads the
 * rest of the engine (functions, auth, security, seo).
 */

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
define('TOOLS_ENV', getenv('TOOLS_ENV') ?: 'production');

/**
 * TOOLS_PLATFORM_URL — the site's base PATH relative to the domain
 * (e.g. "" when installed at the domain root, "/tools" when installed
 * at www.beglet.com/tools). Auto-detected by diffing this project's
 * real filesystem path against the web server's DOCUMENT_ROOT, so
 * uploading this folder into a subfolder "just works" — CSS/JS/tool
 * links never need a hardcoded domain or path.
 *
 * Auto-detection is skipped only if you set the TOOLS_PLATFORM_URL
 * environment variable yourself (rare — e.g. behind a rewriting
 * reverse proxy). If your host ever reports DOCUMENT_ROOT in a way
 * that breaks detection, uncomment and hardcode the line below instead.
 */
// define('TOOLS_PLATFORM_URL', '/tools'); // <- manual override, else auto-detected
if (!defined('TOOLS_PLATFORM_URL')) {
    $envBasePath = getenv('TOOLS_PLATFORM_URL');
    if ($envBasePath !== false && $envBasePath !== '') {
        define('TOOLS_PLATFORM_URL', rtrim($envBasePath, '/'));
    } else {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRootReal = $documentRoot !== '' ? (realpath($documentRoot) ?: rtrim(str_replace('\\', '/', $documentRoot), '/')) : '';
        $siteRootReal = realpath(TOOLS_PLATFORM_ROOT) ?: TOOLS_PLATFORM_ROOT;
        $documentRootReal = str_replace('\\', '/', $documentRootReal);
        $siteRootReal = str_replace('\\', '/', $siteRootReal);

        $basePath = '';
        if ($documentRootReal !== '' && str_starts_with($siteRootReal, $documentRootReal)) {
            $basePath = substr($siteRootReal, strlen($documentRootReal));
        }
        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') {
            $basePath = '';
        }
        define('TOOLS_PLATFORM_URL', $basePath);
    }
}

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
require_once __DIR__ . '/crm_auth.php';
require_once __DIR__ . '/crm_functions.php';

tp_load_settings();
