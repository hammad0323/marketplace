<?php
/**
 * Copy this file to config.php (the web installer does it for you)
 * and fill in your database credentials.
 */
if (!defined('PM_APP')) { http_response_code(403); exit; }

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');
define('DB_PORT', 3306);

// Full URL to the application folder, without trailing slash.
// Leave empty to auto-detect (works on most hosts).
define('BASE_URL', '');

// Random secret used for CSRF tokens / hashing. Change it!
define('APP_KEY', 'change-me-to-a-long-random-string');

// Public proposal links use /proposal/view/{token} (needs mod_rewrite / .htaccess).
// Set to false to use view.php?token={token} instead.
define('PRETTY_URLS', true);

define('APP_TIMEZONE', 'UTC');
define('APP_DEBUG', false);
