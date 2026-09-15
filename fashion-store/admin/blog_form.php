<?php
$pageTitle = 'Blog Post';
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$post = $id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM blog_posts WHERE id = $id")) : null;
if ($id && !$post) redirect('blog.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    $categoryId = !empty($_POST['blog_category_id']) ? (int)$_POST['blog_category_id'] : null;
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $author = trim($_POST['author'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDescription = trim($_POST['seo_description'] ?? '');
    $status = $_POST['status'] === 'published' ? 'published' : 'draft';

    if ($title === '') {
        flash_set('danger', 'Title is required.');
        redirect('blog_form.php' . ($id ? "?id=$id" : ''));
    }

    $slug = unique_slug($mysqli, 'blog_posts', slugify($title), $id);
    $image = handle_upload('featured_image', 'blog');

    if ($id) {
        if ($image) {
            $stmt = mysqli_prepare($mysqli, "UPDATE blog_posts SET blog_category_id=?, title=?, slug=?, excerpt=?, content=?, featured_image=?, author=?, tags=?, seo_title=?, seo_description=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'issssssssssi', $categoryId, $title, $slug, $excerpt, $content, $image, $author, $tags, $seoTitle, $seoDescription, $status, $id);
        } else {
            $stmt = mysqli_prepare($mysqli, "UPDATE blog_posts SET blog_category_id=?, title=?, slug=?, excerpt=?, content=?, author=?, tags=?, seo_title=?, seo_description=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'isssssssssi', $categoryId, $title, $slug, $excerpt, $content, $author, $tags, $seoTitle, $seoDescription, $status, $id);
        }
        mysqli_stmt_execute($stmt);
    } else {
        $image = $image ?: null;
        $stmt = mysqli_prepare($mysqli, "INSERT INTO blog_posts (blog_category_id, title, slug, excerpt, content, featured_image, author, tags, seo_title, seo_description, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'issssssssss', $categoryId, $title, $slug, $excerpt, $content, $image, $author, $tags, $seoTitle, $seoDescription, $status);
        mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($mysqli);
    }
    flash_set('success', 'Blog post saved.');
    redirect('blog_form.php?id=' . $id);
}

$categories = mysqli_query($mysqli, "SELECT * FROM blog_categories ORDER BY name");
?>
<h1 class="page-title mb-4"><?= $id ? 'Edit Post' : 'New Post' ?></h1>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="admin-card mb-4">
      <label class="form-label">Title</label>
      <input type="text" name="title" class="form-control mb-3" required value="<?= e($post['title'] ?? '') ?>">
      <label class="form-label">Excerpt</label>
      <textarea name="excerpt" class="form-control mb-3" rows="2"><?= e($post['excerpt'] ?? '') ?></textarea>
      <label class="form-label">Content</label>
      <textarea name="content" class="form-control richtext" rows="12"><?= $post['content'] ?? '' ?></textarea>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="admin-card mb-4">
      <label class="form-label">Status</label>
      <select name="status" class="form-select mb-3">
        <option value="draft" <?= (($post['status'] ?? 'draft')==='draft')?'selected':'' ?>>Draft</option>
        <option value="published" <?= (($post['status'] ?? '')==='published')?'selected':'' ?>>Published</option>
      </select>
      <label class="form-label">Category</label>
      <select name="blog_category_id" class="form-select mb-3">
        <option value="">None</option>
        <?php while ($c = mysqli_fetch_assoc($categories)): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (($post['blog_category_id'] ?? 0)==$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label class="form-label">Author</label>
      <input type="text" name="author" class="form-control mb-3" value="<?= e($post['author'] ?? get_setting('store_name')) ?>">
      <label class="form-label">Featured Image</label>
      <input type="file" name="featured_image" class="form-control mb-3">
      <?php if (!empty($post['featured_image'])): ?><img src="<?= e(BASE_URL.'/'.$post['featured_image']) ?>" class="thumb-preview mb-3"><?php endif; ?>
      <label class="form-label">Tags</label>
      <input type="text" name="tags" class="form-control mb-3" value="<?= e($post['tags'] ?? '') ?>">
      <button class="btn btn-primary text-white w-100">Save Post</button>
    </div>
    <div class="admin-card">
      <label class="form-label">SEO Title</label>
      <input type="text" name="seo_title" class="form-control mb-3" value="<?= e($post['seo_title'] ?? '') ?>">
      <label class="form-label">SEO Description</label>
      <textarea name="seo_description" class="form-control" rows="2"><?= e($post['seo_description'] ?? '') ?></textarea>
    </div>
  </div>
</div>
</form>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
