<?php

class BusinessProfile extends Model
{
    protected string $table = 'business_profiles';
    protected string $primaryKey = 'vendor_id';

    public function forVendor(int $vendorId): ?array
    {
        $profile = $this->find($vendorId);
        if (!$profile) {
            return null;
        }

        $profile['business_hours'] = $profile['business_hours'] ? json_decode($profile['business_hours'], true) : [];

        return $profile;
    }

    public function saveForVendor(int $vendorId, array $data): void
    {
        if (isset($data['business_hours']) && is_array($data['business_hours'])) {
            $data['business_hours'] = json_encode($data['business_hours']);
        }

        $exists = $this->find($vendorId);
        if ($exists) {
            $this->update($vendorId, $data);
        } else {
            $this->insert($data + ['vendor_id' => $vendorId]);
        }
    }
}
