<?php
/**
 * Generic ?slug= category resolver — since categories are scoped per
 * marketplace, this looks the slug up across marketplace types and
 * redirects into the marketplace-specific category page that actually
 * renders the themed listing.
 */
require __DIR__ . '/../config/config.php';

$slug = $_GET['slug'] ?? '';

$match = mp_db_fetch_one(
    'SELECT categories.slug, marketplace_types.slug AS marketplace_slug
     FROM categories
     JOIN marketplace_types ON marketplace_types.id = categories.marketplace_type_id
     WHERE categories.slug = ? AND categories.is_active = 1
     LIMIT 1',
    [$slug]
);

if (!$match) {
    require __DIR__ . '/../404.php';
    return;
}

$marketplaceRoutes = [
    'artisan'  => ROUTE_ARTISAN,
    'business' => ROUTE_BUSINESS,
];

mp_redirect(($marketplaceRoutes[$match['marketplace_slug']] ?? ROUTE_HOME) . "category.php?slug={$match['slug']}");
