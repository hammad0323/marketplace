<?php
/**
 * Generic /category/{slug} resolver used for the platform-wide SEO URL
 * (e.g. /category/home-decor). Since categories are scoped per
 * marketplace, this looks the slug up across marketplace types and
 * redirects into the marketplace-specific category page that actually
 * renders the themed listing.
 */

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
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

mp_redirect("/{$match['marketplace_slug']}/category/{$match['slug']}");
