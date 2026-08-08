<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$id = (int) ($_POST['id'] ?? 0);

$stmt = mysqli_prepare($db, 'SELECT title, featured_image FROM blog_posts WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$post = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$post) {
    json_response(false, [], 'Post not found.');
}

$stmt = mysqli_prepare($db, 'DELETE FROM blog_posts WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($post['featured_image']) {
    $fullPath = UPLOAD_PATH . '/' . $post['featured_image'];
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

log_activity((int) $_SESSION['user_id'], 'admin', 'delete_blog_post', "Deleted blog post: {$post['title']}");
json_response(true, [], 'Post deleted.');
