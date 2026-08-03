<?php

/**
 * Independent landing experience for the Artisan Marketplace: its own
 * hero, featured categories, featured artists and trending products —
 * deliberately not sharing a template with the Business marketplace so
 * the "premium, emotional, handcrafted" feel stays distinct.
 */
class ArtisanMarketplaceController
{
    private function marketplaceType(): array
    {
        $type = (new MarketplaceType())->findBySlug('artisan');
        if (!$type) {
            http_response_code(500);
            exit('Artisan marketplace type is not seeded. Run database/migrate.php --seed.');
        }
        return $type;
    }

    public function landing(): void
    {
        $type = $this->marketplaceType();
        $vendorModel = new Vendor();
        $productModel = new Product();
        $categoryModel = new Category();

        View::render('artisan/landing', [
            'title' => 'Artisan Marketplace — Handmade with Heart',
            'categories' => $categoryModel->activeByMarketplace($type['id']),
            'featuredArtisans' => $vendorModel->approvedByMarketplace($type['id'], 8),
            'trendingProducts' => $productModel->published($type['id'], 12),
        ], 'artisan');
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

        View::render('artisan/category', [
            'title' => $category['name'] . ' — Artisan Marketplace',
            'category' => $category,
            'products' => $productModel->byCategory($category['id']),
        ], 'artisan');
    }

    public function store(array $params): void
    {
        (new StoreController())->showArtisan($params);
    }
}
