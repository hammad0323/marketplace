<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$staticUrls = ['/index.php', '/shop.php', '/blog.php', '/contact.php'];
foreach ($staticUrls as $u) {
    echo "  <url><loc>" . e(BASE_URL . $u) . "</loc></url>\n";
}

$products = mysqli_query($mysqli, "SELECT slug, updated_at FROM products WHERE status = 'active'");
while ($p = mysqli_fetch_assoc($products)) {
    echo "  <url><loc>" . e(BASE_URL . '/product.php?slug=' . $p['slug']) . "</loc><lastmod>" . e(date('Y-m-d', strtotime($p['updated_at']))) . "</lastmod></url>\n";
}

$categories = mysqli_query($mysqli, "SELECT slug FROM categories WHERE status = 'active'");
while ($c = mysqli_fetch_assoc($categories)) {
    echo "  <url><loc>" . e(BASE_URL . '/shop.php?category=' . $c['slug']) . "</loc></url>\n";
}

$pages = mysqli_query($mysqli, "SELECT slug FROM pages WHERE status = 'active'");
while ($p = mysqli_fetch_assoc($pages)) {
    echo "  <url><loc>" . e(BASE_URL . '/page.php?slug=' . $p['slug']) . "</loc></url>\n";
}

$posts = mysqli_query($mysqli, "SELECT slug FROM blog_posts WHERE status = 'published'");
while ($p = mysqli_fetch_assoc($posts)) {
    echo "  <url><loc>" . e(BASE_URL . '/blog_post.php?slug=' . $p['slug']) . "</loc></url>\n";
}

echo '</urlset>';
