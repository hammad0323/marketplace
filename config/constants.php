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
