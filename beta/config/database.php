<?php
/**
 * MySQLi database connection. Edit these four values for your server.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'beglet');
define('DB_USER', 'root');
define('DB_PASS', '');

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #f3c2c5;background:#fcebec;border-radius:8px;color:#8a161d;">'
        . '<h2 style="margin-top:0;">Database connection failed</h2>'
        . '<p>Could not connect to MySQL using the credentials in <code>config/database.php</code>. '
        . 'Double check DB_HOST/DB_NAME/DB_USER/DB_PASS and that <code>database/beglet.sql</code> has been imported.</p></div>');
}

mysqli_set_charset($conn, 'utf8mb4');
