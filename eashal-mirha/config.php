<?php
/**
 * Eashal Mirha — configuration.
 *
 * The web installer (/install) writes these values for you.
 * You can also edit them by hand: put your MySQL details below and
 * import install/schema.sql + install/seed.sql with phpMyAdmin.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Leave empty to auto-detect (works for root domains and sub-folders).
// Example: https://www.eashalmirha.com
define('SITE_URL', '');

// Set to false on the live site once everything works.
define('DEBUG', false);

date_default_timezone_set('Asia/Karachi');
