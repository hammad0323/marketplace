<?php
/** XML sitemap generated from published, indexable content. */
header('Content-Type: application/xml; charset=utf-8');
$urls = [[url(), date('Y-m-d'), '1.0'], [url('shop'), date('Y-m-d'), '0.9'], [url('new-arrivals'), date('Y-m-d'), '0.8'], [url('collections'), date('Y-m-d'), '0.7']];
foreach (db_all("SELECT slug, COALESCE(updated_at, created_at) m FROM categories WHERE is_active = 1 AND noindex = 0 ORDER BY sort_order") as $r) {
    $urls[] = [url('category/' . $r['slug']), substr($r['m'], 0, 10), '0.8'];
}
foreach (db_all("SELECT slug, COALESCE(updated_at, created_at) m FROM collections WHERE is_active = 1 AND noindex = 0") as $r) {
    $urls[] = [url('collections/' . $r['slug']), substr($r['m'], 0, 10), '0.7'];
}
$products = db_all("SELECT p.id, p.slug, p.name, COALESCE(p.updated_at, p.created_at) m FROM products p WHERE p.status = 'published' AND p.noindex = 0 ORDER BY p.id DESC");
$imgs = [];
foreach (db_all("SELECT product_id, image FROM product_images WHERE is_main = 1 OR sort_order = 0") as $i) {
    $imgs[(int) $i['product_id']] = $imgs[(int) $i['product_id']] ?? $i['image'];
}
foreach (db_all("SELECT slug, COALESCE(updated_at, created_at) m FROM pages WHERE status = 'published' AND noindex = 0") as $r) {
    $urls[] = [url($r['slug']), substr($r['m'], 0, 10), '0.5'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as [$loc, $mod, $pri]) {
    echo '<url><loc>' . e($loc) . '</loc><lastmod>' . e($mod) . '</lastmod><priority>' . $pri . "</priority></url>\n";
}
foreach ($products as $p) {
    echo '<url><loc>' . e(url('product/' . $p['slug'])) . '</loc><lastmod>' . e(substr($p['m'], 0, 10)) . '</lastmod><priority>0.8</priority>';
    if (!empty($imgs[(int) $p['id']])) {
        echo '<image:image><image:loc>' . e(img_url($imgs[(int) $p['id']])) . '</image:loc><image:title>' . e($p['name']) . '</image:title></image:image>';
    }
    echo "</url>\n";
}
echo '</urlset>';
