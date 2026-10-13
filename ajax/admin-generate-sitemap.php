<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

// /sitemap.xml is served live by sitemap.php (see .htaccess), so it can never
// go stale. Remove any old static copy left by earlier versions, which would
// keep showing outdated URLs (e.g. "dr-dr-" doctor links) to crawlers.
$staticPath = dirname(__DIR__) . '/sitemap.xml';
if (is_file($staticPath)) {
    @unlink($staticPath);
}

$llmsTxt = build_llms_txt();
@file_put_contents(dirname(__DIR__) . '/llms.txt', $llmsTxt);

$urlCount = array_sum(array_map('count', sitemap_entries()));
log_activity((int) $_SESSION['user_id'], 'admin', 'generate_sitemap', "Refreshed sitemap ($urlCount URLs) and llms.txt");

json_response(true, [
    'url_count' => $urlCount,
    'sitemap_url' => APP_URL . '/sitemap.xml',
    'generated_at' => date('M j, Y g:i A'),
], "Sitemap is live with $urlCount URLs. llms.txt also refreshed.");
