<?php
/**
 * mysqli database connection. Never accessed directly — pulled in by config.php.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'wanderly';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        exit('Database connection failed. Check config/database.php credentials and that database/schema.sql has been imported. Error: ' . mysqli_connect_error());
    }
    require ROOT_PATH . '/500.php';
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
