<?php
/**
 * Runtime configuration.
 *
 * Values come from environment variables (recommended in production) and fall
 * back to the defaults below. To override without environment variables,
 * create config/config.local.php returning an array, e.g.
 *     <?php return ['DB_PASS' => 'secret', 'APP_URL' => 'https://signage.example.com'];
 * config.local.php is git-ignored.
 */

$__local = is_file(__DIR__ . '/config.local.php') ? (array)require __DIR__ . '/config.local.php' : [];

function cfg_value(string $key, $default, array $local)
{
    if (array_key_exists($key, $local)) return $local[$key];
    $env = getenv($key);
    return ($env === false || $env === '') ? $default : $env;
}

$__defaults = [
    'APP_ENV'          => 'development',          // development | production
    'APP_URL'          => '',                     // e.g. https://signage.example.com/backend  ('' = auto-detect)
    'APP_SECRET'       => 'change-me-to-a-64-char-random-string-before-production-0123456789',
    'DB_HOST'          => '127.0.0.1',
    'DB_PORT'          => '3306',
    'DB_NAME'          => 'signage',
    'DB_USER'          => 'root',
    'DB_PASS'          => '',
    'DB_SOCKET'        => '',
    'CORS_ORIGINS'     => '*',                    // comma list, e.g. https://admin.example.com
    'STORAGE_PATH'     => dirname(__DIR__) . '/uploads',
    'LOG_PATH'         => dirname(__DIR__) . '/logs',
    'MAIL_ENABLED'     => '0',                    // 1 = use PHP mail(); 0 = write mails to logs/mail.log
    'MAIL_FROM'        => 'no-reply@signage.local',
    'TOKEN_TTL_DAYS'   => '30',
    'MEDIA_URL_TTL_DAYS' => '7',
    'WEATHER_PROVIDER' => 'open_meteo',
    'WEATHER_API_KEY'  => '',
];

foreach ($__defaults as $__k => $__v) {
    if (!defined($__k)) define($__k, cfg_value($__k, $__v, $__local));
}
unset($__local, $__defaults, $__k, $__v);

if (APP_ENV === 'production' && str_starts_with(APP_SECRET, 'change-me')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Server is not configured (APP_SECRET).']);
    exit;
}

ini_set('display_errors', APP_ENV === 'production' ? '0' : '0');   // never leak errors into JSON
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
