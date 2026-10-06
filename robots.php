<?php
/**
 * Serves /robots.txt (rewritten here by .htaccess): the static robots.txt
 * rules plus an absolute Sitemap URL — crawlers ignore a relative one, and
 * the domain is only known at request time.
 */
require __DIR__ . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');
$rules = preg_replace('/^Sitemap:.*$/mi', '', (string) file_get_contents(__DIR__ . '/robots.txt'));
echo rtrim($rules) . "\n\nSitemap: " . APP_URL . "/sitemap\n";
