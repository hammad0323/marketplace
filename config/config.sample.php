<?php
/**
 * Urban Collections — configuration.
 *
 * Copy this file to config/config.php and fill in the values.
 * config/config.php is git-ignored and blocked from the web by config/.htaccess.
 */

// --- Database (cPanel » MySQL Databases) -----------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_urban');
define('DB_USER', 'cpaneluser_urban');
define('DB_PASS', 'change-me');
define('DB_PORT', 3306);

// --- Site URL --------------------------------------------------------
// Full public URL with no trailing slash. Use https:// in production.
// If the store lives in a sub-folder use e.g. https://example.com/store
define('SITE_URL', 'https://www.example.com');

// --- Secrets ---------------------------------------------------------
// 64 random hex characters. Generate one with:
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// APP_KEY encrypts payment-gateway credentials stored in the database.
// Changing it later makes saved gateway credentials unreadable (re-enter them).
define('APP_KEY', 'REPLACE_WITH_64_HEX_CHARACTERS');

// --- Environment -----------------------------------------------------
// 'production' hides PHP errors from visitors and logs them to /logs.
define('APP_ENV', 'production');

// Timezone used for orders, reports and delivery estimates.
define('APP_TIMEZONE', 'Asia/Karachi');

// Maximum image upload size in megabytes.
define('UPLOAD_MAX_MB', 5);
