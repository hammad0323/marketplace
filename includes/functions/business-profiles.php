<?php
/**
 * business_profiles — one row per Business Shop vendor.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_business_profile(int $vendorId): ?array
{
    $profile = mp_db_fetch_one('SELECT * FROM business_profiles WHERE vendor_id = ? LIMIT 1', [$vendorId]);
    if (!$profile) {
        return null;
    }

    $profile['business_hours'] = $profile['business_hours'] ? json_decode($profile['business_hours'], true) : [];

    return $profile;
}

function mp_save_business_profile(int $vendorId, array $data): void
{
    if (isset($data['business_hours']) && is_array($data['business_hours'])) {
        $data['business_hours'] = json_encode($data['business_hours']);
    }

    $exists = mp_db_fetch_value('SELECT 1 FROM business_profiles WHERE vendor_id = ?', [$vendorId]);

    if ($exists) {
        mp_db_update('business_profiles', $data, 'vendor_id = ?', [$vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        mp_db_insert('business_profiles', $data);
    }
}
