<?php

class VendorAuthController
{
    public function showRegister(): void
    {
        View::render('vendor/register', [
            'title' => 'Become a Vendor',
            'artisanCategories' => (new Category())->activeByMarketplace(
                (new MarketplaceType())->findBySlug('artisan')['id']
            ),
            'businessCategories' => (new Category())->activeByMarketplace(
                (new MarketplaceType())->findBySlug('business')['id']
            ),
        ], 'main');
    }

    public function register(): void
    {
        verify_csrf();

        $storeName = trim($_POST['store_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $vendorTypeSlug = $_POST['vendor_type'] ?? '';
        $requestedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []);

        if ($storeName === '' || $email === '' || strlen($password) < 8 || !in_array($vendorTypeSlug, ['artisan', 'business'], true)) {
            flash('error', 'Please fill in all required fields (password must be at least 8 characters).');
            redirect('/vendor/register');
        }

        $vendorModel = new Vendor();
        if ($vendorModel->findByEmail($email)) {
            flash('error', 'An account with that email already exists.');
            redirect('/vendor/register');
        }

        $marketplaceType = (new MarketplaceType())->findBySlug($vendorTypeSlug);
        $slugBase = slugify($storeName);
        $slug = $slugBase;
        $suffix = 1;
        while ($vendorModel->findBySlug($slug)) {
            $slug = $slugBase . '-' . (++$suffix);
        }

        $vendorId = $vendorModel->insert([
            'marketplace_type_id' => $marketplaceType['id'],
            'store_name'          => $storeName,
            'slug'                => $slug,
            'email'               => $email,
            'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
            'phone'               => trim($_POST['phone'] ?? '') ?: null,
            'status'              => 'pending',
        ]);

        // Business vendors request the categories they want to sell in;
        // an admin must approve each one before products can use them.
        // Categories share one ID space across marketplace types, so the
        // submitted IDs are filtered down to ones that actually belong
        // to the business marketplace before being stored.
        if ($vendorTypeSlug === 'business' && $requestedCategoryIds) {
            $validCategoryIds = (new Category())->filterIdsByMarketplace($requestedCategoryIds, $marketplaceType['id']);
            if ($validCategoryIds) {
                (new VendorCategoryRequest())->requestCategories($vendorId, $validCategoryIds);
            }
        }

        Notifier::log('vendor.welcome', $email, ['store_name' => $storeName]);
        Notifier::log('admin.new_vendor_registration', 'admin@marketplace.test', ['vendor_id' => $vendorId, 'store_name' => $storeName]);

        Auth::loginVendor(['id' => $vendorId] + $vendorModel->find($vendorId));

        flash('success', 'Registration received! Your store is pending admin approval — you can complete your profile in the meantime.');
        redirect('/vendor/dashboard');
    }

    public function showLogin(): void
    {
        View::render('vendor/login', ['title' => 'Vendor Login'], 'main');
    }

    public function login(): void
    {
        verify_csrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $vendor = (new Vendor())->findByEmail($email);
        if (!$vendor || !password_verify($password, $vendor['password_hash'])) {
            flash('error', 'Invalid email or password.');
            redirect('/vendor/login');
        }

        Auth::loginVendor($vendor);
        redirect('/vendor/dashboard');
    }

    public function logout(): void
    {
        Auth::logoutVendor();
        redirect('/vendor/login');
    }
}
