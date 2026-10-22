<?php
/** robots.txt and ads.txt, managed in Admin » SEO. */
header('Content-Type: text/plain; charset=utf-8');
if ($ROUTE['params']['file'] === 'robots.txt') {
    $robots = setting_bool('seo_noindex_site') ? "User-agent: *\nDisallow: /\n" : (string) setting('robots_txt', "User-agent: *\nDisallow: /admin/\n");
    echo rtrim($robots) . "\n\nSitemap: " . url('sitemap.xml') . "\n";
} else {
    $ads = (string) setting('ads_txt', '');
    if ($ads === '') {
        http_response_code(404);
    }
    echo $ads;
}
