<?php
require __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$urls = [];
$urls[] = ['loc' => base_url(), 'priority' => '1.0'];
$urls[] = ['loc' => base_url('search.php'), 'priority' => '0.6'];

foreach (db_fetch_all("SELECT slug FROM categories WHERE status='active'") as $c) {
    $urls[] = ['loc' => base_url('category.php?slug=' . $c['slug']), 'priority' => '0.8'];
}
foreach (db_fetch_all("SELECT slug FROM subcategories WHERE status='active'") as $sc) {
    $urls[] = ['loc' => base_url('category.php?slug=' . $sc['slug']), 'priority' => '0.7'];
}
foreach (db_fetch_all("SELECT slug FROM shops WHERE status='active'") as $s) {
    $urls[] = ['loc' => base_url('shop.php?slug=' . $s['slug']), 'priority' => '0.7'];
}
foreach (db_fetch_all("SELECT slug, updated_at FROM products WHERE status='active'") as $p) {
    $urls[] = ['loc' => base_url('product.php?slug=' . $p['slug']), 'priority' => '0.6', 'lastmod' => date('Y-m-d', strtotime($p['updated_at']))];
}

set_setting('last_sitemap_generated', date('Y-m-d H:i:s'));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) echo "    <lastmod>{$u['lastmod']}</lastmod>\n";
    echo "    <priority>{$u['priority']}</priority>\n  </url>\n";
}
echo '</urlset>';
