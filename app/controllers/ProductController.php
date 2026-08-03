<?php

class ProductController
{
    public function show(array $params): void
    {
        $product = (new Product())->findBySlug($params['slug']);
        if (!$product || $product['status'] !== 'published') {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        $vendor = (new Vendor())->find($product['vendor_id']);
        $marketplaceType = (new MarketplaceType())->find($product['marketplace_type_id']);
        $category = (new Category())->find($product['category_id']);

        $layout = $marketplaceType['slug'] === 'artisan' ? 'artisan'
            : ($marketplaceType['slug'] === 'business' ? 'business' : 'main');

        View::render('product/show', [
            'title' => $product['title'],
            'product' => $product,
            'vendor' => $vendor,
            'marketplaceType' => $marketplaceType,
            'category' => $category,
        ], $layout);
    }
}
