<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

function sitemap_url($loc, $lastmod = null, $priority = '0.6', $changefreq = 'weekly', $images = []) {
    echo "  <url>\n";
    echo '    <loc>' . e($loc) . "</loc>\n";
    if ($lastmod) echo '    <lastmod>' . e($lastmod) . "</lastmod>\n";
    echo '    <changefreq>' . e($changefreq) . "</changefreq>\n";
    echo '    <priority>' . e($priority) . "</priority>\n";
    foreach ($images as $img) {
        echo "    <image:image>\n      <image:loc>" . e($img) . "</image:loc>\n    </image:image>\n";
    }
    echo "  </url>\n";
}

$logo = get_setting('site_logo');
$logoUrl = $logo ? BASE_URL . '/' . $logo : null;

sitemap_url(url(), date('Y-m-d'), '1.0', 'daily', $logoUrl ? [$logoUrl] : []);
sitemap_url(url('shop'), date('Y-m-d'), '0.9', 'daily');
sitemap_url(url('blog'), date('Y-m-d'), '0.7', 'daily');
sitemap_url(url('contact'), null, '0.4', 'monthly');

$products = mysqli_query($mysqli, "SELECT id, slug, updated_at FROM products WHERE status = 'active'");
while ($p = mysqli_fetch_assoc($products)) {
    $imgRes = mysqli_query($mysqli, "SELECT image_path FROM product_images WHERE product_id = {$p['id']} ORDER BY is_primary DESC, sort_order ASC");
    $images = [];
    while ($img = mysqli_fetch_assoc($imgRes)) $images[] = BASE_URL . '/' . $img['image_path'];
    sitemap_url(product_url($p['slug']), date('Y-m-d', strtotime($p['updated_at'])), '0.8', 'weekly', $images);
}

$categories = mysqli_query($mysqli, "SELECT slug, image FROM categories WHERE status = 'active'");
while ($c = mysqli_fetch_assoc($categories)) {
    $images = $c['image'] ? [BASE_URL . '/' . $c['image']] : [];
    sitemap_url(category_url($c['slug']), null, '0.7', 'weekly', $images);
}

$pages = mysqli_query($mysqli, "SELECT slug, updated_at FROM pages WHERE status = 'active'");
while ($p = mysqli_fetch_assoc($pages)) {
    sitemap_url(page_url($p['slug']), date('Y-m-d', strtotime($p['updated_at'])), '0.5', 'monthly');
}

$posts = mysqli_query($mysqli, "SELECT slug, featured_image, created_at FROM blog_posts WHERE status = 'published'");
while ($p = mysqli_fetch_assoc($posts)) {
    $images = $p['featured_image'] ? [BASE_URL . '/' . $p['featured_image']] : [];
    sitemap_url(blog_url($p['slug']), date('Y-m-d', strtotime($p['created_at'])), '0.6', 'monthly', $images);
}

echo '</urlset>';
