<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => APP_URL . '/index.php', 'priority' => '1.0'],
    ['loc' => APP_URL . '/pages/search.php', 'priority' => '0.6'],
    ['loc' => APP_URL . '/pages/trip-planner.php', 'priority' => '0.8'],
    ['loc' => APP_URL . '/pages/blog.php', 'priority' => '0.6'],
];

foreach (db_select($conn, 'SELECT slug, created_at FROM cities WHERE is_active = 1') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/city.php?slug=' . $row['slug'], 'priority' => '0.8'];
}
foreach (db_select($conn, 'SELECT slug FROM categories WHERE is_active = 1') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/category.php?slug=' . $row['slug'], 'priority' => '0.8'];
}
foreach (db_select($conn, 'SELECT slug, updated_at FROM services WHERE status = "approved"') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/service.php?slug=' . $row['slug'], 'lastmod' => $row['updated_at'], 'priority' => '0.7'];
}
foreach (db_select($conn, 'SELECT slug, updated_at FROM providers WHERE status = "approved"') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/provider.php?slug=' . $row['slug'], 'lastmod' => $row['updated_at'], 'priority' => '0.7'];
}
foreach (db_select($conn, 'SELECT slug FROM blogs WHERE status = "published"') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/blog-post.php?slug=' . $row['slug'], 'priority' => '0.5'];
}
foreach (db_select($conn, 'SELECT slug FROM pages WHERE is_active = 1') as $row) {
    $urls[] = ['loc' => APP_URL . '/pages/page.php?slug=' . $row['slug'], 'priority' => '0.3'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . date('Y-m-d', strtotime($u['lastmod'])) . '</lastmod>' . "\n";
    }
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
