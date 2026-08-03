<?php

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();

// -- Global home & search -----------------------------------------------
$router->get('/', fn () => (new HomeController())->index());
$router->get('/search', fn () => (new SearchController())->index());
$router->get('/category/{slug}', fn ($p) => (new CategoryController())->resolve($p));
$router->get('/product/{slug}', fn ($p) => (new ProductController())->show($p));

// -- Artisan Marketplace ---------------------------------------------------
$router->get('/artisan', fn () => (new ArtisanMarketplaceController())->landing());
$router->get('/artisan/category/{slug}', fn ($p) => (new ArtisanMarketplaceController())->category($p));
$router->get('/artisan/{slug}', fn ($p) => (new ArtisanMarketplaceController())->store($p));

// -- Business Shops ---------------------------------------------------------
$router->get('/business', fn () => (new BusinessMarketplaceController())->landing());
$router->get('/business/category/{slug}', fn ($p) => (new BusinessMarketplaceController())->category($p));
$router->get('/business/{slug}', fn ($p) => (new BusinessMarketplaceController())->store($p));

// -- Official Store ----------------------------------------------------------
$router->get('/store/official-store', fn () => (new StoreController())->showOfficial());

// -- Follow / rate a vendor (customer-gated) ---------------------------------
$router->post('/vendor/{id}/follow', fn ($p) => (new StoreController())->follow($p));

// -- Customer auth ------------------------------------------------------------
$router->get('/customer/register', fn () => (new CustomerAuthController())->showRegister());
$router->post('/customer/register', fn () => (new CustomerAuthController())->register());
$router->get('/customer/login', fn () => (new CustomerAuthController())->showLogin());
$router->post('/customer/login', fn () => (new CustomerAuthController())->login());
$router->post('/customer/logout', fn () => (new CustomerAuthController())->logout());

// -- Vendor auth ---------------------------------------------------------------
$router->get('/vendor/register', fn () => (new VendorAuthController())->showRegister());
$router->post('/vendor/register', fn () => (new VendorAuthController())->register());
$router->get('/vendor/login', fn () => (new VendorAuthController())->showLogin());
$router->post('/vendor/login', fn () => (new VendorAuthController())->login());
$router->post('/vendor/logout', fn () => (new VendorAuthController())->logout());

// -- Vendor dashboard (pending vendors get a restricted view; enforced -------
//    inside the controllers themselves) --------------------------------------
$router->get('/vendor/dashboard', fn () => (new VendorDashboardController())->index());
$router->get('/vendor/dashboard/profile', fn () => (new VendorDashboardController())->showProfile());
$router->post('/vendor/dashboard/profile', fn () => (new VendorDashboardController())->saveProfile());
$router->get('/vendor/dashboard/categories', fn () => (new VendorDashboardController())->showCategories());
$router->post('/vendor/dashboard/categories', fn () => (new VendorDashboardController())->requestCategories());
$router->get('/vendor/dashboard/products', fn () => (new VendorDashboardController())->showProducts());
$router->get('/vendor/dashboard/products/create', fn () => (new VendorDashboardController())->showCreateProduct());
$router->post('/vendor/dashboard/products/create', fn () => (new VendorDashboardController())->createProduct());

// -- Admin ------------------------------------------------------------------
$router->get('/admin/login', fn () => (new AdminAuthController())->showLogin());
$router->post('/admin/login', fn () => (new AdminAuthController())->login());
$router->post('/admin/logout', fn () => (new AdminAuthController())->logout());
$router->get('/admin', fn () => (new AdminDashboardController())->index());
$router->get('/admin/vendors', fn () => (new VendorApprovalController())->index());
$router->post('/admin/vendors/{id}/approve', fn ($p) => (new VendorApprovalController())->approve($p));
$router->post('/admin/vendors/{id}/reject', fn ($p) => (new VendorApprovalController())->reject($p));
$router->get('/admin/category-requests', fn () => (new CategoryApprovalController())->index());
$router->post('/admin/category-requests/{id}/decide', fn ($p) => (new CategoryApprovalController())->decide($p));
$router->post('/admin/category-requests/{id}/toggle', fn ($p) => (new CategoryApprovalController())->toggle($p));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
