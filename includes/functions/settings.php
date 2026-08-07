<?php
/**
 * settings — per-tenant site-wide configuration as key/value rows
 * instead of hardcoded PHP constants, so each tenant's own
 * admin/settings.php can change its own values without a redeploy.
 * Every value is cast back to its real PHP type on read according to
 * its setting_type column. Primary key is (tenant_id, setting_key) —
 * every tenant has its own independent copy of every setting.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_settings_cast(string $value, string $type)
{
    return match ($type) {
        'number'  => $value === '' ? 0 : $value + 0,
        'boolean' => in_array($value, ['1', 'true', 'on', 'yes'], true),
        'json'    => json_decode($value, true) ?? [],
        default   => $value,
    };
}

/** All of this tenant's settings as an associative array of key => (already cast) value. */
function mp_all_settings(): array
{
    $rows = mp_db_fetch_all('SELECT setting_key, setting_value, setting_type FROM settings WHERE tenant_id = ?', [mp_tenant_id()]);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = mp_settings_cast($row['setting_value'] ?? '', $row['setting_type']);
    }

    return $settings;
}

function mp_get_setting(string $key, $default = null)
{
    $row = mp_db_fetch_one(
        'SELECT setting_value, setting_type FROM settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1',
        [mp_tenant_id(), $key]
    );
    if (!$row) {
        return $default;
    }

    return mp_settings_cast($row['setting_value'] ?? '', $row['setting_type']);
}

/** Insert-or-update one of this tenant's settings, inferring setting_type from the PHP value's own type. */
function mp_set_setting(string $key, $value): void
{
    if (is_bool($value)) {
        $type = 'boolean';
        $stored = $value ? '1' : '0';
    } elseif (is_int($value) || is_float($value)) {
        $type = 'number';
        $stored = (string) $value;
    } elseif (is_array($value)) {
        $type = 'json';
        $stored = json_encode($value);
    } else {
        $type = 'string';
        $stored = (string) $value;
    }

    mp_db_execute(
        'INSERT INTO settings (tenant_id, setting_key, setting_value, setting_type) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)',
        [mp_tenant_id(), $key, $stored, $type]
    );
}
