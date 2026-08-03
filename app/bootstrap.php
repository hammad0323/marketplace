<?php
/**
 * App bootstrap: session, error display, autoloading of core/models/controllers.
 * Included once by public/index.php before routes are dispatched.
 */

$config = require __DIR__ . '/config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', $config['app_debug'] ? '1' : '0');

session_name($config['session']['name']);
session_set_cookie_params($config['session']['lifetime']);
session_start();

require __DIR__ . '/config/database.php';
require __DIR__ . '/core/helpers.php';
require __DIR__ . '/core/Router.php';
require __DIR__ . '/core/View.php';
require __DIR__ . '/core/Model.php';
require __DIR__ . '/core/Auth.php';
require __DIR__ . '/core/Notifier.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . "/models/{$class}.php",
        __DIR__ . "/controllers/{$class}.php",
        __DIR__ . "/controllers/Admin/{$class}.php",
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require $path;
            return;
        }
    }
});
