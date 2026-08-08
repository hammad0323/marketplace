<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$id = (int) ($_POST['id'] ?? 0);
$title = clean($_POST['title'] ?? '');
$excerpt = clean($_POST['excerpt'] ?? '');
$content = $_POST['content'] ?? '';
$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

$errors = [];
if ($title === '' || mb_strlen($title) < 5) {
    $errors['title'] = 'Please enter a title (at least 5 characters).';
}
if (trim(strip_tags($content)) === '') {
    $errors['content'] = 'Post content cannot be empty.';
}
if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$featuredImage = null;
if (!empty($_FILES['featured_image']['name'])) {
    [$ok, $result] = handle_upload('featured_image', 'blog', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $featuredImage = $result;
}

$authorId = (int) $_SESSION['user_id'];

if ($id > 0) {
    $stmt = mysqli_prepare($db, 'SELECT featured_image, status FROM blog_posts WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$existing) {
        json_response(false, [], 'Post not found.');
    }
    if ($featuredImage === null) {
        $featuredImage = $existing['featured_image'];
    }
    $publishedAt = null;
    if ($status === 'published' && $existing['status'] !== 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    }

    if ($publishedAt) {
        $stmt = mysqli_prepare($db, 'UPDATE blog_posts SET title=?, excerpt=?, content=?, featured_image=?, status=?, published_at=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssssssi', $title, $excerpt, $content, $featuredImage, $status, $publishedAt, $id);
    } else {
        $stmt = mysqli_prepare($db, 'UPDATE blog_posts SET title=?, excerpt=?, content=?, featured_image=?, status=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'sssssi', $title, $excerpt, $content, $featuredImage, $status, $id);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_activity($authorId, 'admin', 'update_blog_post', "Updated blog post #$id");
    json_response(true, ['id' => $id], 'Post updated.');
}

$slug = unique_slug($db, 'blog_posts', $title);
$publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
$stmt = mysqli_prepare($db, 'INSERT INTO blog_posts (author_id, title, slug, excerpt, content, featured_image, status, published_at) VALUES (?,?,?,?,?,?,?,?)');
mysqli_stmt_bind_param($stmt, 'isssssss', $authorId, $title, $slug, $excerpt, $content, $featuredImage, $status, $publishedAt);
mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

log_activity($authorId, 'admin', 'create_blog_post', "Created blog post #$newId: $title");
json_response(true, ['id' => $newId], 'Post created.');
