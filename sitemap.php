<?php
require __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$staticPages = ['/', '/doctors', '/specializations', '/about', '/contact', '/faq', '/privacy-policy', '/terms', '/doctor-register', '/login', '/register'];

$doctors = mysqli_query(db(), "SELECT slug, updated_at FROM doctors WHERE verification_status = 'verified'");
$specs = mysqli_query(db(), 'SELECT slug FROM specializations WHERE is_active = 1');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPages as $path) {
    echo '<url><loc>' . e(APP_URL . $path) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
}
while ($d = mysqli_fetch_assoc($doctors)) {
    echo '<url><loc>' . e(APP_URL . '/doctor-profile?slug=' . $d['slug']) . '</loc><lastmod>' . date('Y-m-d', strtotime($d['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
}
while ($s = mysqli_fetch_assoc($specs)) {
    echo '<url><loc>' . e(APP_URL . '/doctors?specialization=' . $s['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
}

echo '</urlset>';
