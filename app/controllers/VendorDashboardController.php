<?php

class VendorDashboardController
{
    private function marketplaceSlug(array $vendor): string
    {
        return (new MarketplaceType())->find($vendor['marketplace_type_id'])['slug'];
    }

    public function index(): void
    {
        $vendor = Auth::requireVendor();
        $marketplaceSlug = $this->marketplaceSlug($vendor);

        View::render('vendor/dashboard', [
            'title' => 'Vendor Dashboard',
            'vendor' => $vendor,
            'marketplaceSlug' => $marketplaceSlug,
            'productCount' => count((new Product())->byVendor($vendor['id'])),
            'categoryRequests' => $marketplaceSlug === 'business'
                ? (new VendorCategoryRequest())->forVendor($vendor['id'])
                : [],
        ], 'main');
    }

    public function showProfile(): void
    {
        $vendor = Auth::requireVendor();
        $marketplaceSlug = $this->marketplaceSlug($vendor);

        View::render('vendor/profile', [
            'title' => 'Store Profile',
            'vendor' => $vendor,
            'marketplaceSlug' => $marketplaceSlug,
            'artisanProfile' => $marketplaceSlug === 'artisan' ? (new ArtisanProfile())->forVendor($vendor['id']) : null,
            'businessProfile' => $marketplaceSlug === 'business' ? (new BusinessProfile())->forVendor($vendor['id']) : null,
        ], 'main');
    }

    public function saveProfile(): void
    {
        verify_csrf();
        $vendor = Auth::requireVendor();
        $marketplaceSlug = $this->marketplaceSlug($vendor);

        if ($marketplaceSlug === 'artisan') {
            (new ArtisanProfile())->saveForVendor($vendor['id'], [
                'biography'   => trim($_POST['biography'] ?? ''),
                'brand_story' => trim($_POST['brand_story'] ?? ''),
                'social_links' => [
                    'instagram' => trim($_POST['instagram'] ?? ''),
                    'facebook'  => trim($_POST['facebook'] ?? ''),
                    'pinterest' => trim($_POST['pinterest'] ?? ''),
                ],
            ]);
        } elseif ($marketplaceSlug === 'business') {
            (new BusinessProfile())->saveForVendor($vendor['id'], [
                'business_info'   => trim($_POST['business_info'] ?? ''),
                'contact_email'   => trim($_POST['contact_email'] ?? ''),
                'contact_phone'   => trim($_POST['contact_phone'] ?? ''),
                'contact_address' => trim($_POST['contact_address'] ?? ''),
                'shop_policies'   => trim($_POST['shop_policies'] ?? ''),
                'delivery_info'   => trim($_POST['delivery_info'] ?? ''),
            ]);
        }

        flash('success', 'Profile updated.');
        redirect('/vendor/dashboard/profile');
    }

    public function showCategories(): void
    {
        $vendor = Auth::requireVendor();
        if ($this->marketplaceSlug($vendor) !== 'business') {
            redirect('/vendor/dashboard');
        }

        $categoryModel = new Category();
        $requestModel = new VendorCategoryRequest();
        $requested = $requestModel->forVendor($vendor['id']);
        $requestedIds = array_column($requested, 'category_id');

        $businessType = (new MarketplaceType())->findBySlug('business');

        View::render('vendor/categories', [
            'title' => 'Selling Categories',
            'vendor' => $vendor,
            'requests' => $requested,
            'availableCategories' => array_filter(
                $categoryModel->activeByMarketplace($businessType['id']),
                fn ($c) => !in_array($c['id'], $requestedIds, true)
            ),
        ], 'main');
    }

    public function requestCategories(): void
    {
        verify_csrf();
        $vendor = Auth::requireVendor();
        if ($this->marketplaceSlug($vendor) !== 'business') {
            redirect('/vendor/dashboard');
        }

        $categoryIds = array_map('intval', $_POST['category_ids'] ?? []);
        $validCategoryIds = (new Category())->filterIdsByMarketplace($categoryIds, $vendor['marketplace_type_id']);
        if ($validCategoryIds) {
            (new VendorCategoryRequest())->requestCategories($vendor['id'], $validCategoryIds);
            flash('success', 'Category request submitted for admin approval.');
        }

        redirect('/vendor/dashboard/categories');
    }

    public function showProducts(): void
    {
        $vendor = Auth::requireVendor();

        View::render('vendor/products', [
            'title' => 'My Products',
            'vendor' => $vendor,
            'products' => (new Product())->byVendor($vendor['id']),
        ], 'main');
    }

    public function showCreateProduct(): void
    {
        $vendor = Auth::requireVendor();

        if ($vendor['status'] !== 'approved') {
            flash('error', 'Your store must be approved by an admin before you can add products.');
            redirect('/vendor/dashboard');
        }

        $marketplaceSlug = $this->marketplaceSlug($vendor);
        $categoryModel = new Category();

        if ($marketplaceSlug === 'business') {
            $approvedIds = (new VendorCategoryRequest())->approvedCategoryIds($vendor['id']);
            $categories = $approvedIds
                ? array_filter($categoryModel->allByMarketplace($vendor['marketplace_type_id']), fn ($c) => in_array($c['id'], $approvedIds, true))
                : [];
        } else {
            // Artisan and Official vendors sell within their marketplace's
            // full category set once approved — only Business Shops go
            // through per-category approval.
            $categories = $categoryModel->activeByMarketplace($vendor['marketplace_type_id']);
        }

        View::render('vendor/product_form', [
            'title' => 'Add Product',
            'vendor' => $vendor,
            'categories' => $categories,
        ], 'main');
    }

    public function createProduct(): void
    {
        verify_csrf();
        $vendor = Auth::requireVendor();

        if ($vendor['status'] !== 'approved') {
            redirect('/vendor/dashboard');
        }

        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $marketplaceSlug = $this->marketplaceSlug($vendor);
        $productModel = new Product();

        if ($marketplaceSlug === 'business') {
            $approvedIds = (new VendorCategoryRequest())->approvedCategoryIds($vendor['id']);
            if (!in_array($categoryId, $approvedIds, true)) {
                flash('error', 'You are not approved to sell in that category yet.');
                redirect('/vendor/dashboard/products/create');
            }

            $requestModel = new VendorCategoryRequest();
            $requests = $requestModel->forVendor($vendor['id']);
            foreach ($requests as $request) {
                if ((int) $request['category_id'] === $categoryId && $request['usage_limit'] !== null) {
                    $used = $productModel->countInCategoryForVendor($vendor['id'], $categoryId);
                    if ($used >= (int) $request['usage_limit']) {
                        flash('error', 'You have reached the product limit admin set for this category.');
                        redirect('/vendor/dashboard/products/create');
                    }
                }
            }
        } else {
            $validIds = (new Category())->filterIdsByMarketplace([$categoryId], $vendor['marketplace_type_id']);
            if (!$validIds) {
                flash('error', 'Please choose a valid category.');
                redirect('/vendor/dashboard/products/create');
            }
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '' || $categoryId <= 0) {
            flash('error', 'Title and category are required.');
            redirect('/vendor/dashboard/products/create');
        }

        $slugBase = slugify($title);
        $slug = $slugBase;
        $suffix = 1;
        while ($productModel->findBySlug($slug)) {
            $slug = $slugBase . '-' . (++$suffix);
        }

        $images = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));

        $productModel->insert([
            'vendor_id'           => $vendor['id'],
            'category_id'         => $categoryId,
            'marketplace_type_id' => $vendor['marketplace_type_id'],
            'title'               => $title,
            'slug'                => $slug,
            'description'         => trim($_POST['description'] ?? ''),
            'price'               => (float) ($_POST['price'] ?? 0),
            'images'              => json_encode(array_values($images)),
            'status'              => 'published',
        ]);

        flash('success', 'Product added.');
        redirect('/vendor/dashboard/products');
    }
}
