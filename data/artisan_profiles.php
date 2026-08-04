<?php
/** Plain query functions for artisan_profiles (JSON columns decoded on read). */

const MP_ARTISAN_PROFILE_JSON_COLUMNS = [
    'workshop_images', 'gallery_images', 'process_media',
    'achievements', 'portfolio_items', 'social_links',
];

function mp_find_artisan_profile(int $vendorId): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM artisan_profiles WHERE vendor_id = :vendor_id LIMIT 1');
    $stmt->execute(['vendor_id' => $vendorId]);
    $profile = $stmt->fetch();
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

    $exists = mp_db()->prepare('SELECT 1 FROM artisan_profiles WHERE vendor_id = :vendor_id');
    $exists->execute(['vendor_id' => $vendorId]);

    if ($exists->fetchColumn()) {
        $assignments = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $stmt = mp_db()->prepare("UPDATE artisan_profiles SET {$assignments} WHERE vendor_id = :vendor_id");
        $stmt->execute($data + ['vendor_id' => $vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ":{$c}", $columns);
        $stmt = mp_db()->prepare(
            'INSERT INTO artisan_profiles (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($data);
    }
}
