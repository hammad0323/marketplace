<?php
require __DIR__ . '/../config.php';
wh_require_page_access('blog');
$businessId = wh_current_business_id();

$id = (int) wh_input_get('id', 0);
$post = $id ? wh_fetch_one('SELECT * FROM blog_posts WHERE id=? AND business_id=?', 'ii', [$id, $businessId]) : null;
if ($id && !$post) {
    wh_flash_set('error', 'Post not found.');
    wh_redirect(BASE_URL . '/admin/blog.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $title = wh_input_post('title');
    if ($title === '') $errors[] = 'Title is required.';

    if (!$errors) {
        $status = wh_input_post('status') === 'published' ? 'published' : 'draft';
        $uploaded = wh_handle_image_upload('featured_image', 'blog', 'blog');
        $slugBase = wh_slugify($title);
        $slug = $slugBase;
        $i = 1;
        while (wh_fetch_one('SELECT id FROM blog_posts WHERE business_id=? AND slug=? AND id!=?', 'isi', [$businessId, $slug, $id])) {
            $i++; $slug = $slugBase . '-' . $i;
        }
        $data = [
            'title' => $title, 'slug' => $slug, 'excerpt' => wh_input_post('excerpt'),
            'content' => $_POST['content'] ?? '', 'category' => wh_input_post('category'),
            'status' => $status, 'seo_title' => wh_input_post('seo_title'), 'meta_description' => wh_input_post('meta_description'),
        ];
        if ($status === 'published') {
            $data['published_at'] = $post['published_at'] ?? date('Y-m-d H:i:s');
        }
        if ($uploaded) $data['featured_image'] = $uploaded;

        if ($id) {
            wh_update('blog_posts', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
            wh_flash_set('success', 'Post updated.');
        } else {
            $data['business_id'] = $businessId;
            wh_insert('blog_posts', $data);
            wh_flash_set('success', 'Post created.');
        }
        wh_redirect(BASE_URL . '/admin/blog.php');
    }
}

$pageTitle = $id ? 'Edit Post' : 'New Post';
$activePage = 'blog';
require __DIR__ . '/header.php';
?>
<form method="post" enctype="multipart/form-data">
  <?= wh_csrf_field() ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <div class="admin-card">
    <div class="form-group"><label>Title *</label><input type="text" name="title" value="<?= e($post['title'] ?? '') ?>" required></div>
    <div class="form-grid">
      <div class="form-group"><label>Category</label><input type="text" name="category" value="<?= e($post['category'] ?? '') ?>"></div>
      <div class="form-group"><label>Status</label><select name="status"><option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></div>
    </div>
    <div class="form-group"><label>Excerpt</label><textarea name="excerpt" rows="2"><?= e($post['excerpt'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Content (HTML allowed)</label><textarea name="content" rows="12"><?= e($post['content'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Featured Image</label><input type="file" name="featured_image" accept="image/*">
      <?php if (!empty($post['featured_image'])): ?><br><img src="<?= e(BASE_URL . '/' . $post['featured_image']) ?>" style="height:70px;margin-top:6px;border-radius:6px;"><?php endif; ?>
    </div>
  </div>
  <div class="admin-card">
    <h3 style="margin-bottom:16px;">SEO</h3>
    <div class="form-group"><label>SEO Title</label><input type="text" name="seo_title" value="<?= e($post['seo_title'] ?? '') ?>"></div>
    <div class="form-group"><label>Meta Description</label><textarea name="meta_description" rows="2"><?= e($post['meta_description'] ?? '') ?></textarea></div>
  </div>
  <button type="submit" class="btn btn-primary">Save Post</button>
  <a href="<?= e(BASE_URL) ?>/admin/blog.php" class="btn btn-light">Cancel</a>
</form>
<?php require __DIR__ . '/footer.php'; ?>
