<?php

/**
 * Renders a single vendor's public store page — artist story page for
 * Artisan vendors, standard shop page for Business vendors, and the
 * single platform Official Store.
 */
class StoreController
{
    private function loadApprovedVendorBySlugAndType(string $slug, string $marketplaceSlug): ?array
    {
        $type = (new MarketplaceType())->findBySlug($marketplaceSlug);
        if (!$type) {
            return null;
        }

        $vendor = (new Vendor())->findBySlug($slug);
        if (!$vendor || (int) $vendor['marketplace_type_id'] !== (int) $type['id'] || $vendor['status'] !== 'approved') {
            return null;
        }

        return $vendor;
    }

    public function showArtisan(array $params): void
    {
        $vendor = $this->loadApprovedVendorBySlugAndType($params['slug'], 'artisan');
        if (!$vendor) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        $vendorModel = new Vendor();
        $profile = (new ArtisanProfile())->forVendor($vendor['id']);
        $products = (new Product())->byVendor($vendor['id']);

        View::render('store/artisan', [
            'title' => $vendor['store_name'] . ' — Artisan Story',
            'vendor' => $vendor,
            'profile' => $profile,
            'products' => $products,
            'rating' => $vendorModel->averageRating($vendor['id']),
            'followers' => $vendorModel->followerCount($vendor['id']),
        ], 'artisan');
    }

    public function showBusiness(array $params): void
    {
        $vendor = $this->loadApprovedVendorBySlugAndType($params['slug'], 'business');
        if (!$vendor) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        $vendorModel = new Vendor();
        $profile = (new BusinessProfile())->forVendor($vendor['id']);
        $products = (new Product())->byVendor($vendor['id']);

        View::render('store/business', [
            'title' => $vendor['store_name'] . ' — Shop',
            'vendor' => $vendor,
            'profile' => $profile,
            'products' => $products,
            'rating' => $vendorModel->averageRating($vendor['id']),
        ], 'business');
    }

    public function showOfficial(): void
    {
        $vendor = (new Vendor())->findBySlug('official-store');
        if (!$vendor) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        $profile = (new BusinessProfile())->forVendor($vendor['id']);
        $products = (new Product())->byVendor($vendor['id']);

        View::render('store/official', [
            'title' => $vendor['store_name'] . ' — Official Store',
            'vendor' => $vendor,
            'profile' => $profile,
            'products' => $products,
        ], 'main');
    }

    public function follow(array $params): void
    {
        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            redirect('/customer/login');
        }

        verify_csrf();

        $vendor = (new Vendor())->find((int) $params['id']);
        if ($vendor) {
            (new Customer())->follow($customerId, $vendor['id']);
            flash('success', 'You are now following ' . $vendor['store_name'] . '.');
        }

        redirect($_POST['redirect_to'] ?? '/');
    }
}
