<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
header('Content-Type: text/plain; charset=utf-8');
$bp = base_path();
echo "User-agent: *\n";
foreach (['admin/', 'api/', 'cart/', 'checkout/', 'my-account/', 'order-success/', 'login/', 'register/', 'logout/', 'wishlist/', 'track-order/', 'search/'] as $d) {
    echo 'Disallow: ' . $bp . '/' . $d . "\n";
}
echo "Disallow: /*?*sort=\nDisallow: /*?*min=\nDisallow: /*?*brand\n";
echo "Allow: $bp/\n\n";
$extra = trim((string) setting('robots_extra', ''));
if ($extra !== '') echo $extra . "\n\n";
echo 'Sitemap: ' . abs_url('sitemap.xml') . "\n";
