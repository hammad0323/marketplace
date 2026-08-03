<?php
/**
 * Front controller. This is the one piece of routing "glue" in the
 * app — everything else is plain PHP page files organized by feature
 * folder under modules/. Routes are matched by regex; named capture
 * groups (e.g. {slug}, {id}) become plain variables the matched page
 * file can use directly.
 */

require __DIR__ . '/../includes/bootstrap.php';

$routes = [
    '#^/$#'                                              => '/../modules/home/index.php',
    '#^/search$#'                                        => '/../modules/search/index.php',
    '#^/category/(?<slug>[^/]+)$#'                       => '/../modules/category/resolve.php',
    '#^/product/(?<slug>[^/]+)$#'                        => '/../modules/product/show.php',

    '#^/artisan$#'                                       => '/../modules/artisan/landing.php',
    '#^/artisan/category/(?<slug>[^/]+)$#'               => '/../modules/artisan/category.php',
    '#^/artisan/(?<slug>[^/]+)$#'                        => '/../modules/artisan/store.php',

    '#^/business$#'                                      => '/../modules/business/landing.php',
    '#^/business/category/(?<slug>[^/]+)$#'              => '/../modules/business/category.php',
    '#^/business/(?<slug>[^/]+)$#'                       => '/../modules/business/store.php',

    '#^/store/official-store$#'                          => '/../modules/official-store/index.php',

    '#^/vendor/(?<id>\d+)/follow$#'                      => '/../modules/vendor/follow.php',

    '#^/customer/register$#'                             => '/../modules/customer/register.php',
    '#^/customer/login$#'                                => '/../modules/customer/login.php',
    '#^/customer/logout$#'                               => '/../modules/customer/logout.php',

    '#^/vendor/register$#'                               => '/../modules/vendor/register.php',
    '#^/vendor/login$#'                                  => '/../modules/vendor/login.php',
    '#^/vendor/logout$#'                                 => '/../modules/vendor/logout.php',
    '#^/vendor/dashboard$#'                              => '/../modules/vendor/dashboard.php',
    '#^/vendor/dashboard/profile$#'                      => '/../modules/vendor/profile.php',
    '#^/vendor/dashboard/categories$#'                   => '/../modules/vendor/categories.php',
    '#^/vendor/dashboard/products$#'                     => '/../modules/vendor/products.php',
    '#^/vendor/dashboard/products/create$#'              => '/../modules/vendor/product-form.php',

    '#^/admin/login$#'                                   => '/../modules/admin/login.php',
    '#^/admin/logout$#'                                  => '/../modules/admin/logout.php',
    '#^/admin$#'                                         => '/../modules/admin/dashboard.php',
    '#^/admin/vendors$#'                                 => '/../modules/admin/vendors.php',
    '#^/admin/vendors/(?<id>\d+)/approve$#'              => '/../modules/admin/vendor-approve.php',
    '#^/admin/vendors/(?<id>\d+)/reject$#'               => '/../modules/admin/vendor-reject.php',
    '#^/admin/category-requests$#'                       => '/../modules/admin/category-requests.php',
    '#^/admin/category-requests/(?<id>\d+)/decide$#'     => '/../modules/admin/category-decide.php',
    '#^/admin/category-requests/(?<id>\d+)/toggle$#'     => '/../modules/admin/category-toggle.php',
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
require __DIR__ . '/../partials/404.php';
