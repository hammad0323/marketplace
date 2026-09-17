<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$businessId = DEFAULT_BUSINESS_ID;
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /ajax/
Disallow: /uploads/

Sitemap: <?= e(BASE_URL) ?>/sitemap.xml
