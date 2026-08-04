<?php
/**
 * Database credentials + PDO connection singleton.
 *
 * Everything here is meant to be edited for your environment — nothing
 * elsewhere in the app hardcodes a connection. On a shared host you'll
 * typically only need to change these four constants.
 *
 * For production, consider moving these into environment variables
 * (getenv('DB_HOST') etc., already wired up below) instead of
 * committing real credentials.
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'marketplace');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

/**
 * Returns a shared PDO connection, created on first use.
 */
function mp_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (ini_get('display_errors')) {
                die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #f3c2c5;background:#fcebec;border-radius:8px;color:#8a161d;">'
                    . '<h2 style="margin-top:0;">Database connection failed</h2>'
                    . '<p>Could not connect to MySQL/MariaDB using the credentials in <code>config/database.php</code>.</p>'
                    . '<p>Make sure the database server is running, the database has been imported '
                    . '(<code>php database/migrate.php --seed</code>, or <code>database/full-install.sql</code> via phpMyAdmin), '
                    . 'and DB_HOST/DB_NAME/DB_USER/DB_PASS are correct.</p>'
                    . '<p style="color:#a34;font-size:13px;">' . htmlspecialchars($e->getMessage()) . '</p></div>');
            }
            die('Database connection failed.');
        }
    }

    return $pdo;
}
