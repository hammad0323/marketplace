<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$businessId = DEFAULT_BUSINESS_ID;
?>
User-agent: *
Allow: <?= e(APP_PATH) ?>/
Disallow: <?= e(APP_PATH) ?>/admin/
Disallow: <?= e(APP_PATH) ?>/ajax/
Disallow: <?= e(APP_PATH) ?>/uploads/

Sitemap: <?= e(BASE_URL) ?>/sitemap.xml
