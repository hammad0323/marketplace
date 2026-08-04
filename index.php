<?php
/**
 * Front controller — lives at the project root so hosts that can only
 * point a domain at one fixed folder can serve this directly. This is
 * the one piece of routing "glue" in the app; everything else is
 * plain PHP page files organized by feature folder (pages/, vendor/,
 * account/, admin/). Routes are matched by regex; named capture
 * groups (e.g. {slug}, {id}) become plain variables the matched page
 * file can use directly.
 */

// Apache's .htaccess already serves real files (assets/, etc.) directly
// and blocks direct access to app internals, never reaching this script
// for either. PHP's built-in dev server (`php -S`) has no such rules, so
// replicate both here — harmless in production since Apache never gets
// this far for them.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $requestedFile = __DIR__ . $requestPath;

    // Only real files are subject to either rule below — extension-less
    // clean URLs like /admin/login never match an actual file, so they
    // correctly fall through to the router regardless of which folder
    // name they start with.
    if (is_file($requestedFile)) {
        if (preg_match('#^/(includes|data|config|services|pages|vendor|account|admin|database|logs)/#', $requestPath)) {
            http_response_code(403);
            exit('Forbidden');
        }
        if ($requestedFile !== __FILE__) {
            return false;
        }
    }
}

require __DIR__ . '/config.php';

$routes = [
    '#^/$#'                                              => '/pages/home.php',
    '#^/search$#'                                        => '/pages/search.php',
    '#^/category/(?<slug>[^/]+)$#'                       => '/pages/category.php',
    '#^/product/(?<slug>[^/]+)$#'                        => '/pages/product.php',

    '#^/artisan$#'                                       => '/pages/artisan-landing.php',
    '#^/artisan/category/(?<slug>[^/]+)$#'               => '/pages/artisan-category.php',
    '#^/artisan/(?<slug>[^/]+)$#'                        => '/pages/artisan-store.php',

    '#^/business$#'                                      => '/pages/business-landing.php',
    '#^/business/category/(?<slug>[^/]+)$#'              => '/pages/business-category.php',
    '#^/business/(?<slug>[^/]+)$#'                       => '/pages/business-store.php',

    '#^/store/official-store$#'                          => '/pages/official-store.php',

    '#^/vendor/(?<id>\d+)/follow$#'                      => '/account/follow.php',

    '#^/customer/register$#'                             => '/pages/customer-register.php',
    '#^/customer/login$#'                                => '/pages/customer-login.php',
    '#^/customer/logout$#'                               => '/pages/customer-logout.php',

    '#^/vendor/register$#'                               => '/pages/vendor-register.php',
    '#^/vendor/login$#'                                  => '/pages/vendor-login.php',
    '#^/vendor/logout$#'                                 => '/pages/vendor-logout.php',
    '#^/vendor/dashboard$#'                              => '/vendor/dashboard.php',
    '#^/vendor/dashboard/profile$#'                      => '/vendor/profile.php',
    '#^/vendor/dashboard/categories$#'                   => '/vendor/categories.php',
    '#^/vendor/dashboard/products$#'                     => '/vendor/products.php',
    '#^/vendor/dashboard/products/create$#'              => '/vendor/product-form.php',

    '#^/admin/login$#'                                   => '/admin/login.php',
    '#^/admin/logout$#'                                  => '/admin/logout.php',
    '#^/admin$#'                                         => '/admin/dashboard.php',
    '#^/admin/vendors$#'                                 => '/admin/vendors.php',
    '#^/admin/vendors/(?<id>\d+)/approve$#'              => '/admin/vendor-approve.php',
    '#^/admin/vendors/(?<id>\d+)/reject$#'               => '/admin/vendor-reject.php',
    '#^/admin/category-requests$#'                       => '/admin/category-requests.php',
    '#^/admin/category-requests/(?<id>\d+)/decide$#'     => '/admin/category-decide.php',
    '#^/admin/category-requests/(?<id>\d+)/toggle$#'     => '/admin/category-toggle.php',
];

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}

foreach ($routes as $pattern => $file) {
    if (preg_match($pattern, $path, $matches)) {
        $params = array_filter($matches, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        extract($params);
        require __DIR__ . $file;
        exit;
    }
}

http_response_code(404);
require __DIR__ . '/pages/404.php';
