<?php
/**
 * Global configuration & bootstrap. Every page on the site requires
 * this ONE file at the top — it starts the session, connects to the
 * database, and loads every helper/data function used everywhere else
 * (see functions.php).
 *
 * ============================================================
 *  TO DEPLOY: edit the four DB_* constants below to match your
 *  database, then import database.sql. That's it — nothing else
 *  in this project needs to be edited or moved.
 * ============================================================
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

define('SITE_NAME', 'Marketplace');

/**
 * Returns a shared PDO connection, created on first use.
 */
function mp_db(): PDO
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
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #f3c2c5;background:#fcebec;border-radius:8px;color:#8a161d;">'
                . '<h2 style="margin-top:0;">Database connection failed</h2>'
                . '<p>Could not connect to MySQL using the credentials in <code>config.php</code>. '
                . 'Double check DB_HOST/DB_NAME/DB_USER/DB_PASS and that <code>database.sql</code> has been imported.</p>'
                . '<p style="color:#a34;font-size:13px;">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }

    return $pdo;
}

require __DIR__ . '/functions.php';
