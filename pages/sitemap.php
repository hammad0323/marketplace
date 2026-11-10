<?php
/** Dynamic XML sitemap (/sitemap.xml) */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
header('Content-Type: application/xml; charset=utf-8');
$urls = [];
$add = function (string $loc, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.6', ?string $image = null) use (&$urls) {
    $urls[] = compact('loc', 'lastmod', 'freq', 'prio', 'image');
};
$add(abs_url(''), date('Y-m-d'), 'daily', '1.0');
foreach (['shop/', 'monthly-rashan/', 'offers/', 'brands/', 'contact/'] as $p) $add(abs_url($p), null, 'daily', '0.8');
foreach (categories_all() as $c) {
    if (!category_is_visible((int) $c['id'])) continue;
    $robots = db_val("SELECT meta_robots FROM seo_metadata WHERE entity_type = 'category' AND entity_id = ? AND entity_key = ''", [$c['id']]);
    if ($robots && strpos($robots, 'noindex') !== false) continue;
    $add(site_origin() . category_url($c), substr((string) ($c['updated_at'] ?: $c['created_at']), 0, 10), 'weekly', $c['parent_id'] ? '0.7' : '0.9');
}
$rows = db_all("SELECT p.slug, p.updated_at, p.created_at, (SELECT image FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order LIMIT 1) AS image,
    (SELECT meta_robots FROM seo_metadata s WHERE s.entity_type = 'product' AND s.entity_id = p.id AND s.entity_key = '') AS robots
    FROM products p WHERE p.status = 'active' AND p.deleted_at IS NULL ORDER BY p.id");
foreach ($rows as $r) {
    if ($r['robots'] && strpos($r['robots'], 'noindex') !== false) continue;
    $add(abs_url('product/' . $r['slug'] . '/'), substr((string) ($r['updated_at'] ?: $r['created_at']), 0, 10), 'weekly', '0.8', $r['image'] ? site_origin() . upload_url($r['image']) : null);
}
foreach (db_all('SELECT slug, updated_at, created_at FROM brands WHERE is_active = 1 AND deleted_at IS NULL') as $b) $add(abs_url('brand/' . $b['slug'] . '/'), substr((string) ($b['updated_at'] ?: $b['created_at']), 0, 10), 'monthly', '0.5');
foreach (db_all('SELECT slug, updated_at, created_at FROM promotions WHERE ' . sql_active_window()) as $p) $add(abs_url('offers/' . $p['slug'] . '/'), substr((string) ($p['updated_at'] ?: $p['created_at']), 0, 10), 'weekly', '0.6');
foreach (db_all('SELECT slug, updated_at, created_at FROM pages WHERE is_active = 1') as $p) $add(abs_url($p['slug'] . '/'), substr((string) ($p['updated_at'] ?: $p['created_at']), 0, 10), 'monthly', '0.4');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
    if ($u['lastmod']) echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
    echo '<changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority>';
    if ($u['image']) echo '<image:image><image:loc>' . htmlspecialchars($u['image'], ENT_XML1) . '</image:loc></image:image>';
    echo "</url>\n";
}
echo '</urlset>';
