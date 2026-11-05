<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/config.php';
require APP_ROOT . '/config/constants.php';
require APP_ROOT . '/config/database.php';
require APP_ROOT . '/includes/response.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/upload.php';
function out(string $m): void { echo '[' . gmdate('c') . "] {$m}\n"; }
