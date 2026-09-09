<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$xml = build_sitemap_xml();
$path = dirname(__DIR__) . '/sitemap.xml';

if (@file_put_contents($path, $xml) === false) {
    json_response(false, [], 'Could not write sitemap.xml — check that the web server has write permission on the project root.');
}

$llmsTxt = build_llms_txt();
$llmsPath = dirname(__DIR__) . '/llms.txt';
@file_put_contents($llmsPath, $llmsTxt);

$urlCount = substr_count($xml, '<url>');
log_activity((int) $_SESSION['user_id'], 'admin', 'generate_sitemap', "Generated static sitemap.xml ($urlCount URLs) and llms.txt");

json_response(true, [
    'url_count' => $urlCount,
    'sitemap_url' => APP_URL . '/sitemap.xml',
    'generated_at' => date('M j, Y g:i A'),
], "Sitemap generated with $urlCount URLs. llms.txt also refreshed.");
