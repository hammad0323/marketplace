<?php

class MarketplaceType extends Model
{
    protected string $table = 'marketplace_types';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }
}
