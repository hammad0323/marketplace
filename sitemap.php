<?php
require __DIR__ . '/config.php';
header('Content-Type: application/xml; charset=utf-8');
$businessId = DEFAULT_BUSINESS_ID;

$staticPages = ['', 'about', 'halls', 'gallery', 'availability', 'booking', 'contact', 'faq', 'privacy', 'terms', 'blog'];
$halls = wh_get_halls($businessId, true);
$posts = wh_fetch_all(
    "SELECT slug, updated_at FROM blog_posts WHERE business_id = ? AND status = 'published'",
    'i',
    [$businessId]
);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= e(BASE_URL . '/' . $page) ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= $page === '' ? '1.0' : '0.7' ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($halls as $hall): ?>
  <url>
    <loc><?= e(BASE_URL . '/hall/' . $hall['slug']) ?></loc>
    <lastmod><?= e(date('Y-m-d', strtotime($hall['updated_at']))) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
<?php foreach ($posts as $post): ?>
  <url>
    <loc><?= e(BASE_URL . '/blog/' . $post['slug']) ?></loc>
    <lastmod><?= e(date('Y-m-d', strtotime($post['updated_at']))) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; ?>
</urlset>
