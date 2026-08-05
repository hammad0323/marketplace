<?php
/**
 * artisan_profiles — one row per Artisan vendor. Several columns store
 * JSON arrays (gallery images, social links, etc.), decoded on read.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

const MP_ARTISAN_PROFILE_JSON_COLUMNS = [
    'workshop_images', 'gallery_images', 'process_media',
    'achievements', 'portfolio_items', 'social_links',
];

function mp_find_artisan_profile(int $vendorId): ?array
{
    $profile = mp_db_fetch_one('SELECT * FROM artisan_profiles WHERE vendor_id = ? LIMIT 1', [$vendorId]);
    if (!$profile) {
        return null;
    }

    foreach (MP_ARTISAN_PROFILE_JSON_COLUMNS as $column) {
        $profile[$column] = $profile[$column] ? json_decode($profile[$column], true) : [];
    }

    return $profile;
}

function mp_save_artisan_profile(int $vendorId, array $data): void
{
    foreach (MP_ARTISAN_PROFILE_JSON_COLUMNS as $column) {
        if (isset($data[$column]) && is_array($data[$column])) {
            $data[$column] = json_encode($data[$column]);
        }
    }

    $exists = mp_db_fetch_value('SELECT 1 FROM artisan_profiles WHERE vendor_id = ?', [$vendorId]);

    if ($exists) {
        mp_db_update('artisan_profiles', $data, 'vendor_id = ?', [$vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        mp_db_insert('artisan_profiles', $data);
    }
}
