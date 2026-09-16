<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');

echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /ajax/\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /cart\n";
echo "Disallow: /checkout\n";
echo "Disallow: /account/\n";
echo "Disallow: /invoice/\n";
echo "Allow: /\n";

$extra = trim(get_setting('robots_extra_rules'));
if ($extra) {
    echo "\n" . $extra . "\n";
}

echo "\nSitemap: " . url('sitemap.xml') . "\n";
