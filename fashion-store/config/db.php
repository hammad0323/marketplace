<?php
require_once __DIR__ . '/config.php';

$mysqli = mysqli_init();
$connected = @mysqli_real_connect($mysqli, DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$connected) {
    http_response_code(500);
    if (APP_ENV === 'production') {
        die('Database connection failed. Please check your configuration in config/config.php.');
    }
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($mysqli, 'utf8mb4');
