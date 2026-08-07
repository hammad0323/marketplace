<?php
/**
 * Site-wide constants. No database credentials here — those live in
 * database.php. Values that should be editable from the future Admin
 * Panel "Settings" screens will move into a database-backed settings
 * table; until that exists, they're defined here as the single source
 * of truth so nothing is hardcoded inline in page files.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

define('SITE_NAME', 'Marketplace');

define('ERROR_REPORTING_ENABLED', true);

/**
 * The base domain this multi-tenant install is deployed under. Every
 * signed-up tenant is reached at {subdomain}.APP_BASE_DOMAIN (see
 * config/tenant.php) — a single wildcard DNS record (e.g. "*.marketplace.test")
 * pointed at this same document root is all that's needed; no per-tenant
 * virtual host or database is required. Edit this to your real domain
 * at deploy, exactly like the DB_* constants in config/database.php.
 */
define('APP_BASE_DOMAIN', 'marketplace.test');
