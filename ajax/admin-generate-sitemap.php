<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$urlCount = write_static_sitemap();
if ($urlCount === false) {
    json_response(false, [], 'Could not write sitemap.xml — check that the web server has write permission on the website root folder (public_html).');
}

@file_put_contents(dirname(__DIR__) . '/llms.txt', build_llms_txt());

log_activity((int) $_SESSION['user_id'], 'admin', 'generate_sitemap', "Generated sitemap.xml ($urlCount URLs) and llms.txt");

json_response(true, [
    'url_count' => $urlCount,
    'sitemap_url' => APP_URL . '/sitemap.xml',
    'generated_at' => date('M j, Y g:i A'),
], "Sitemap generated with $urlCount URLs. llms.txt also refreshed.");
