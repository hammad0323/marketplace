<?php
/**
 * Webanza Tech — global configuration & bootstrap.
 *
 * ============================================================
 *  TO DEPLOY: edit the DB_* constants below, then either
 *  import database.sql in phpMyAdmin or open /install.php once.
 * ============================================================
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

// Optional local overrides (not committed) — handy for development.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'your_database_name');
defined('DB_USER') || define('DB_USER', 'your_database_user');
defined('DB_PASS') || define('DB_PASS', 'your_database_password');

// Set to false on the live server once everything works.
defined('APP_DEBUG') || define('APP_DEBUG', true);

// Leave empty to auto-detect. Set it (e.g. 'https://webanzatech.com')
// if links/images point to the wrong address.
defined('BASE_URL') || define('BASE_URL', '');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', __DIR__ . '/uploads');

/**
 * Shared PDO connection, created on first use.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;padding:28px;border-radius:14px;background:#fff4f4;border:1px solid #f3c2c5;color:#7a1219">'
                . '<h2 style="margin-top:0">Database connection failed</h2>'
                . '<p>Check the <code>DB_*</code> settings in <code>config.php</code> and make sure <code>database.sql</code> was imported (or run <code>install.php</code>).</p>'
                . (APP_DEBUG ? '<p style="font-size:13px;opacity:.8">' . htmlspecialchars($e->getMessage()) . '</p>' : '')
                . '</div>');
        }
    }
    return $pdo;
}

require __DIR__ . '/functions.php';
