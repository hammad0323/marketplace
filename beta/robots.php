<?php
require __DIR__ . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');
?>
User-agent: *
Disallow: <?= parse_url(admin_url(), PHP_URL_PATH) ?>
Disallow: <?= parse_url(base_url('config/'), PHP_URL_PATH) ?>
Disallow: <?= parse_url(base_url('actions/'), PHP_URL_PATH) ?>
Disallow: <?= parse_url(shop_url('dashboard.php'), PHP_URL_PATH) ?>
Disallow: <?= parse_url(customer_url('dashboard.php'), PHP_URL_PATH) ?>
Disallow: <?= parse_url(employee_url('dashboard.php'), PHP_URL_PATH) ?>
Allow: /

Sitemap: <?= base_url('sitemap.php') ?>
