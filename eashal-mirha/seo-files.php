<?php
/**
 * Live fallback for /robots.txt, /ads.txt and /sitemap.xml when the
 * static files have not been generated yet (see Admin → SEO Tools).
 */
define('NO_PRETTY_REDIRECT', true);
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

switch (get('f')) {
    case 'robots':
        header('Content-Type: text/plain; charset=utf-8');
        echo robots_txt();
        break;
    case 'ads':
        header('Content-Type: text/plain; charset=utf-8');
        echo trim((string)setting('ads_txt', '')) . "\n";
        break;
    case 'sitemap':
        header('Content-Type: application/xml; charset=utf-8');
        echo sitemap_xml();
        break;
    default:
        http_response_code(404);
}
