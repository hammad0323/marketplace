<?php

/**
 * Independent landing experience for Business Shops — clean, modern,
 * commercial. Mirrors ArtisanMarketplaceController's shape but renders
 * with the 'business' layout/theme and business-flavoured copy.
 */
class BusinessMarketplaceController
{
    private function marketplaceType(): array
    {
        $type = (new MarketplaceType())->findBySlug('business');
        if (!$type) {
            http_response_code(500);
            exit('Business marketplace type is not seeded. Run database/migrate.php --seed.');
        }
        return $type;
    }

    public function landing(): void
    {
        $type = $this->marketplaceType();
        $vendorModel = new Vendor();
        $productModel = new Product();
        $categoryModel = new Category();

        View::render('business/landing', [
            'title' => 'Business Shops — Shop Trusted Retail Stores',
            'categories' => $categoryModel->activeByMarketplace($type['id']),
            'featuredShops' => $vendorModel->approvedByMarketplace($type['id'], 8),
            'bestSellers' => $productModel->bestSellers($type['id'], 8),
            'trendingProducts' => $productModel->published($type['id'], 12),
        ], 'business');
    }

    public function category(array $params): void
    {
        $type = $this->marketplaceType();
        $categoryModel = new Category();
        $productModel = new Product();

        $category = $categoryModel->findBySlugInMarketplace($params['slug'], $type['id']);
        if (!$category) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        View::render('business/category', [
            'title' => $category['name'] . ' — Business Shops',
            'category' => $category,
            'products' => $productModel->byCategory($category['id']),
        ], 'business');
    }

    public function store(array $params): void
    {
        (new StoreController())->showBusiness($params);
    }
}
