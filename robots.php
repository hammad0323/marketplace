<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: text/plain; charset=UTF-8');
?>
User-agent: *
Disallow: <?php echo BASE_PATH; ?>/admin/
Disallow: <?php echo BASE_PATH; ?>/ajax/
Disallow: <?php echo BASE_PATH; ?>/config/
Disallow: <?php echo BASE_PATH; ?>/includes/
Disallow: <?php echo BASE_PATH; ?>/customer/
Disallow: <?php echo BASE_PATH; ?>/provider/
Allow: <?php echo BASE_PATH; ?>/

Sitemap: <?php echo APP_URL; ?>/sitemap.xml
