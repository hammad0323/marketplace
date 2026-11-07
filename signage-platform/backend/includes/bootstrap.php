<?php
/**
 * Every API endpoint starts with:
 *     require __DIR__ . '/../../includes/bootstrap.php';
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config/config.php';
require APP_ROOT . '/config/constants.php';
require APP_ROOT . '/config/database.php';
require __DIR__ . '/response.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/validation.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/permissions.php';
require __DIR__ . '/rate_limit.php';
require __DIR__ . '/upload.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/publish.php';
require __DIR__ . '/schedule.php';
require __DIR__ . '/display_codes.php';

set_exception_handler(function (Throwable $e) {
    $ref = substr(random_token(4), 0, 8);
    app_log("[{$ref}] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(), 'error.log');
    if ($e instanceof mysqli_sql_exception && in_array($e->getCode(), [1062], true)) {
        fail('This item already exists', 409, 'CONFLICT');
    }
    if ($e instanceof mysqli_sql_exception && in_array($e->getCode(), [1451], true)) {
        fail('This item is still in use and cannot be deleted', 409, 'IN_USE');
    }
    if ($e instanceof mysqli_sql_exception && in_array($e->getCode(), [2002, 1045, 1049], true)) {
        fail('The service is temporarily unavailable', 503, 'DB_UNAVAILABLE');
    }
    fail("Something went wrong on our side (ref {$ref}). Please try again.", 500, 'SERVER_ERROR');
});

set_error_handler(function (int $no, string $str, string $file, int $line) {
    if (!(error_reporting() & $no)) return false;
    throw new ErrorException($str, 0, $no, $file, $line);
});

send_cors_headers();
