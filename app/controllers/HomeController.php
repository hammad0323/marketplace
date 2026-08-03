<?php

class HomeController
{
    public function index(): void
    {
        $marketplaceTypeModel = new MarketplaceType();
        $vendorModel = new Vendor();
        $productModel = new Product();

        $artisanType = $marketplaceTypeModel->findBySlug('artisan');
        $businessType = $marketplaceTypeModel->findBySlug('business');
        $officialType = $marketplaceTypeModel->findBySlug('official');

        View::render('home/index', [
            'title' => 'Discover Handmade Artisans & Trusted Business Shops',
            'featuredArtisans' => $artisanType ? $vendorModel->approvedByMarketplace($artisanType['id'], 6) : [],
            'featuredBusinesses' => $businessType ? $vendorModel->approvedByMarketplace($businessType['id'], 6) : [],
            'officialStore' => $officialType ? ($vendorModel->approvedByMarketplace($officialType['id'], 1)[0] ?? null) : null,
            'trendingArtisanProducts' => $artisanType ? $productModel->published($artisanType['id'], 8) : [],
            'trendingBusinessProducts' => $businessType ? $productModel->published($businessType['id'], 8) : [],
        ], 'main');
    }
}
