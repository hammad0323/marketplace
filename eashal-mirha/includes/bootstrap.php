<?php
/**
 * Loaded at the top of every page (storefront and admin).
 */
define('ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

require ROOT . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');

// Gateways POST back cross-site without our cookie; starting a session there
// would replace the shopper's session, so the return handler skips it.
if (session_status() === PHP_SESSION_NONE && !defined('NO_SESSION')) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('EMSESS');
    session_start();
}

require ROOT . '/includes/functions.php';
require ROOT . '/includes/payments.php';

// Not installed yet → send to the installer.
if (DB_NAME === '' && !defined('IS_INSTALLER')) {
    header('Location: ' . base_path() . '/install/');
    exit;
}

// Pretty URLs: /shop.php?x=1 → /shop?x=1 (GET only, keeps POST forms safe).
if (!defined('IS_INSTALLER') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if (preg_match('~\.php$~', $path) && !defined('NO_PRETTY_REDIRECT')) {
        $clean = preg_replace('~(/index)?\.php$~', '', $path);
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . ($clean === '' ? '/' : $clean) . ($qs !== '' ? '?' . $qs : ''), true, 301);
        exit;
    }
}
