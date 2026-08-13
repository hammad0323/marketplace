<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? db_select_one($conn, 'SELECT * FROM blogs WHERE id = ?', [$id]) : null;
if ($id && !$post) {
    flash_set('danger', 'Post not found.');
    redirect('/admin/blog.php');
}

$errors = [];
$postTags = $post ? implode(', ', array_column(db_select($conn, 'SELECT t.name FROM blog_tag_map m JOIN blog_tags t ON t.id = m.tag_id WHERE m.blog_id = ?', [(int) $post['id']]), 'name')) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = clean_input($_POST['title'] ?? '');
    $categoryName = clean_input($_POST['category_name'] ?? '');
    $excerpt = clean_input($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $seoTitle = clean_input($_POST['seo_title'] ?? '');
    $seoDescription = clean_input($_POST['seo_description'] ?? '');
    $tagsInput = clean_input($_POST['tags'] ?? '');

    if (!require_field($title, 'Title', $errors)) {
        // no-op, error already added
    }

    $imagePath = $post['featured_image'] ?? null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = upload_file('featured_image', 'blog');
        if (!$upload['ok'] && $upload['error']) {
            $errors[] = $upload['error'];
        } elseif ($upload['ok']) {
            $imagePath = $upload['path'];
        }
    }

    if (!$errors) {
        $categoryId = null;
        if ($categoryName !== '') {
            $existingCat = db_select_one($conn, 'SELECT id FROM blog_categories WHERE name = ?', [$categoryName]);
            $categoryId = $existingCat ? (int) $existingCat['id'] : db_insert_get_id($conn, 'INSERT INTO blog_categories (name, slug) VALUES (?, ?)', [$categoryName, slugify($categoryName)]);
        }

        $slug = unique_slug($conn, 'blogs', $title, $post['id'] ?? null);
        $publishedAt = $status === 'published' ? ($post['published_at'] ?? date('Y-m-d H:i:s')) : null;

        if ($post) {
            db_execute(
                $conn,
                'UPDATE blogs SET category_id=?, title=?, slug=?, featured_image=?, excerpt=?, content=?, status=?, seo_title=?, seo_description=?, published_at=? WHERE id=?',
                [$categoryId, $title, $slug, $imagePath, $excerpt, $content, $status, $seoTitle, $seoDescription, $publishedAt, (int) $post['id']]
            );
            $postId = (int) $post['id'];
        } else {
            $postId = db_insert_get_id(
                $conn,
                'INSERT INTO blogs (author_id, category_id, title, slug, featured_image, excerpt, content, status, seo_title, seo_description, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [(int) $admin['id'], $categoryId, $title, $slug, $imagePath, $excerpt, $content, $status, $seoTitle, $seoDescription, $publishedAt]
            );
        }

        db_execute($conn, 'DELETE FROM blog_tag_map WHERE blog_id = ?', [$postId]);
        foreach (array_filter(array_map('trim', explode(',', $tagsInput))) as $tagName) {
            $existingTag = db_select_one($conn, 'SELECT id FROM blog_tags WHERE name = ?', [$tagName]);
            $tagId = $existingTag ? (int) $existingTag['id'] : db_insert_get_id($conn, 'INSERT INTO blog_tags (name, slug) VALUES (?, ?)', [$tagName, slugify($tagName)]);
            db_execute($conn, 'INSERT IGNORE INTO blog_tag_map (blog_id, tag_id) VALUES (?, ?)', [$postId, $tagId]);
        }

        log_audit($conn, (int) $admin['id'], 'blog', $postId, 'save');
        flash_set('success', 'Post saved.');
        redirect('/admin/blog-form.php?id=' . $postId);
    }
}

$existingCategory = $post && $post['category_id'] ? db_select_one($conn, 'SELECT name FROM blog_categories WHERE id = ?', [(int) $post['category_id']]) : null;

$adminPageTitle = $post ? 'Edit Post' : 'New Post';
$adminActive = 'blog';
require __DIR__ . '/_layout_top.php';
?>

<a href="<?php echo url('/admin/blog.php'); ?>" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to posts</a>

<div class="panel" style="margin-top:16px;max-width:800px;">
  <?php foreach ($errors as $err): ?><div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div><?php endforeach; ?>

  <form method="post" class="form-w" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <label>Title</label>
    <input type="text" name="title" value="<?php echo e($post['title'] ?? ''); ?>" required>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>Category (type new or existing)</label><input type="text" name="category_name" value="<?php echo e($existingCategory['name'] ?? ''); ?>" placeholder="e.g. City Guides"></div>
      <div><label>Tags (comma-separated)</label><input type="text" name="tags" value="<?php echo e($postTags); ?>" placeholder="beach, budget-travel"></div>
    </div>
    <label>Excerpt</label>
    <input type="text" name="excerpt" maxlength="300" value="<?php echo e($post['excerpt'] ?? ''); ?>">
    <label>Content (HTML)</label>
    <textarea name="content" rows="12"><?php echo e($post['content'] ?? ''); ?></textarea>
    <label>Featured image</label>
    <input type="file" name="featured_image" accept="image/png,image/jpeg,image/webp">
    <?php if (!empty($post['featured_image'])): ?><div class="form-hint">Current: <?php echo e($post['featured_image']); ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>SEO title</label><input type="text" name="seo_title" value="<?php echo e($post['seo_title'] ?? ''); ?>"></div>
      <div><label>SEO description</label><input type="text" name="seo_description" value="<?php echo e($post['seo_description'] ?? ''); ?>"></div>
    </div>
    <label>Status</label>
    <select name="status">
      <option value="draft" <?php echo ($post['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
      <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
    </select>
    <button type="submit" class="btn-w btn-primary" style="margin-top:20px;"><?php echo $post ? 'Save changes' : 'Create post'; ?></button>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
