<?php
/**
 * Ebaya configuration.
 *
 * Copy this file to config/config.php and fill in your real values.
 * config/config.php is blocked from web access by .htaccess and is
 * ignored by git — never commit real credentials.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

// Database (cPanel → MySQL Databases)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_PORT', 3306);

// Full public URL of the store, no trailing slash.
// Examples: 'https://ebaya.pk'  or  'https://example.com/ebaya'
define('SITE_URL', 'https://example.com');

// 32+ random bytes, base64. Used to encrypt payment gateway secrets.
// Generate with:  php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
// Changing it later makes saved gateway secrets unreadable (re-enter them).
define('APP_KEY', 'CHANGE_ME_TO_A_RANDOM_BASE64_KEY');

// 'production' hides errors from visitors and logs them to storage/logs.
define('APP_ENV', 'production');

// Timezone used for orders, reports and delivery estimates.
define('APP_TIMEZONE', 'Asia/Karachi');

// Force the session cookie to be HTTPS-only. Keep true on a live site with SSL.
define('FORCE_HTTPS', true);
