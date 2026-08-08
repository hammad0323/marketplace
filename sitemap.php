<?php
require __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');
echo build_sitemap_xml();
