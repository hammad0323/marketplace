<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$slug = $_POST['slug'] ?? '';
$allowedSlugs = ['about', 'privacy-policy', 'terms-conditions'];
if (!in_array($slug, $allowedSlugs, true)) {
    json_response(false, [], 'Invalid page.');
}

$title = clean($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$metaTitle = clean($_POST['meta_title'] ?? '');
$metaDescription = clean($_POST['meta_description'] ?? '');

if ($title === '' || trim(strip_tags($content)) === '') {
    json_response(false, [], 'Title and content are required.');
}

$stmt = mysqli_prepare(db(), 'UPDATE cms_pages SET title = ?, content = ?, meta_title = ?, meta_description = ? WHERE slug = ?');
mysqli_stmt_bind_param($stmt, 'sssss', $title, $content, $metaTitle, $metaDescription, $slug);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'update_cms', "Updated CMS page: $slug");
json_response(true, [], 'Page updated.');
