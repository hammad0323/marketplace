<?php
/** Plain query functions for business_profiles. */

function mp_find_business_profile(int $vendorId): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM business_profiles WHERE vendor_id = :vendor_id LIMIT 1');
    $stmt->execute(['vendor_id' => $vendorId]);
    $profile = $stmt->fetch();
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

    $exists = mp_db()->prepare('SELECT 1 FROM business_profiles WHERE vendor_id = :vendor_id');
    $exists->execute(['vendor_id' => $vendorId]);

    if ($exists->fetchColumn()) {
        $assignments = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $stmt = mp_db()->prepare("UPDATE business_profiles SET {$assignments} WHERE vendor_id = :vendor_id");
        $stmt->execute($data + ['vendor_id' => $vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ":{$c}", $columns);
        $stmt = mp_db()->prepare(
            'INSERT INTO business_profiles (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($data);
    }
}
