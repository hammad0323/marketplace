<?php
require __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');
// /sitemap is an index of per-type sitemaps (/sitemap?type=doctors ...), so Search
// Console reports indexing coverage separately for doctors, specialities, blog, etc.
$type = $_GET['type'] ?? '';
if ($type === '') {
    echo build_sitemap_index_xml();
} elseif (in_array($type, ['pages', 'doctors', 'specialities', 'blog', 'medicines', 'pharmacies', 'products'], true)) {
    echo build_sitemap_xml($type);
} else {
    http_response_code(404);
    echo build_sitemap_index_xml();
}
