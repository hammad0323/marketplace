<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

/**
 * Strips the block editor's internal, non-persisted `_uid` key (used only
 * client-side to track DOM nodes) from every block before it's saved.
 */
function sanitize_blog_blocks_json($raw)
{
    $blocks = json_decode($raw ?? '', true);
    if (!is_array($blocks)) {
        return null;
    }
    foreach ($blocks as &$b) {
        if (is_array($b)) {
            unset($b['_uid']);
        }
    }
    unset($b);
    return $blocks ? json_encode($blocks) : null;
}

$db = db();
$id = (int) ($_POST['id'] ?? 0);
$title = clean($_POST['title'] ?? '');
$excerpt = clean($_POST['excerpt'] ?? '');
$category = clean($_POST['category'] ?? '') ?: null;
$content = $_POST['content'] ?? '';
$blocks = sanitize_blog_blocks_json($_POST['blocks'] ?? null);
$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
$metaTitle = clean($_POST['meta_title'] ?? '') ?: null;
$metaDescription = clean($_POST['meta_description'] ?? '') ?: null;

$errors = [];
if ($title === '' || mb_strlen($title) < 5) {
    $errors['title'] = 'Please enter a title (at least 5 characters).';
}
if ($blocks === null && trim(strip_tags($content)) === '') {
    $errors['content'] = 'Add at least one block with content.';
}
if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$featuredImage = null;
if (!empty($_FILES['featured_image']['name'])) {
    [$ok, $result] = handle_upload('featured_image', 'blog', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024, [1200, 1200]);
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
        $stmt = mysqli_prepare($db, 'UPDATE blog_posts SET title=?, excerpt=?, content=?, blocks=?, category=?, featured_image=?, status=?, meta_title=?, meta_description=?, published_at=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssssssssssi', $title, $excerpt, $content, $blocks, $category, $featuredImage, $status, $metaTitle, $metaDescription, $publishedAt, $id);
    } else {
        $stmt = mysqli_prepare($db, 'UPDATE blog_posts SET title=?, excerpt=?, content=?, blocks=?, category=?, featured_image=?, status=?, meta_title=?, meta_description=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'sssssssssi', $title, $excerpt, $content, $blocks, $category, $featuredImage, $status, $metaTitle, $metaDescription, $id);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_activity($authorId, 'admin', 'update_blog_post', "Updated blog post #$id");
    json_response(true, ['id' => $id], 'Post updated.');
}

$slug = unique_slug($db, 'blog_posts', $title);
$publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
$stmt = mysqli_prepare($db, 'INSERT INTO blog_posts (author_id, title, slug, excerpt, content, blocks, category, featured_image, status, meta_title, meta_description, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
mysqli_stmt_bind_param($stmt, 'isssssssssss', $authorId, $title, $slug, $excerpt, $content, $blocks, $category, $featuredImage, $status, $metaTitle, $metaDescription, $publishedAt);
mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

log_activity($authorId, 'admin', 'create_blog_post', "Created blog post #$newId: $title");
json_response(true, ['id' => $newId], 'Post created.');
