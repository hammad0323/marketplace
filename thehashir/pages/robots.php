<?php
header('Content-Type: text/plain; charset=utf-8');
if (BETA_MODE) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\nDisallow: /api/\nDisallow: /search\nDisallow: /config/\nDisallow: /includes/\n\nSitemap: " . SITE_URL . "/sitemap.xml\n";
