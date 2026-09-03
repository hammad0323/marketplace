<?php
/**
 * Generic ?slug= category resolver — since categories are scoped per
 * marketplace, this looks the slug up across marketplace types and
 * redirects into the marketplace-specific category page that actually
 * renders the themed listing.
 */
require __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';

$stmt = mp_db()->prepare(
    'SELECT categories.slug, marketplace_types.slug AS marketplace_slug
     FROM categories
     JOIN marketplace_types ON marketplace_types.id = categories.marketplace_type_id
     WHERE categories.slug = :slug AND categories.is_active = 1
     LIMIT 1'
);
$stmt->execute(['slug' => $slug]);
$match = $stmt->fetch();

if (!$match) {
    require __DIR__ . '/404.php';
    return;
}

mp_redirect("/{$match['marketplace_slug']}-category.php?slug={$match['slug']}");
