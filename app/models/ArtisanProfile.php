<?php

class ArtisanProfile extends Model
{
    protected string $table = 'artisan_profiles';
    protected string $primaryKey = 'vendor_id';

    private array $jsonColumns = [
        'workshop_images', 'gallery_images', 'process_media',
        'achievements', 'portfolio_items', 'social_links',
    ];

    public function forVendor(int $vendorId): ?array
    {
        $profile = $this->find($vendorId);
        if (!$profile) {
            return null;
        }

        foreach ($this->jsonColumns as $column) {
            $profile[$column] = $profile[$column] ? json_decode($profile[$column], true) : [];
        }

        return $profile;
    }

    public function saveForVendor(int $vendorId, array $data): void
    {
        foreach ($this->jsonColumns as $column) {
            if (isset($data[$column]) && is_array($data[$column])) {
                $data[$column] = json_encode($data[$column]);
            }
        }

        $exists = $this->find($vendorId);
        if ($exists) {
            $this->update($vendorId, $data);
        } else {
            $this->insert($data + ['vendor_id' => $vendorId]);
        }
    }
}
