<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? tp_query_one('SELECT * FROM blog_posts WHERE id = ?', 'i', [$id]) : null;
$seo = $id ? (get_seo_settings('blog_post', $id) ?? []) : [];
$linkedToolIds = $id ? array_column(tp_query('SELECT tool_id FROM blog_post_tools WHERE blog_post_id = ?', 'i', [$id]), 'tool_id') : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $title = tp_sanitize_text($_POST['title'] ?? '', 220);
    $slugInput = tp_sanitize_text($_POST['slug'] ?? '', 240);
    $excerpt = tp_sanitize_text($_POST['excerpt'] ?? '', 400);
    $contentHtml = $_POST['content'] ?? '';
    $categoryId = (int) ($_POST['blog_category_id'] ?? 0) ?: null;
    $featuredImage = tp_sanitize_text($_POST['featured_image'] ?? '', 255);
    $status = in_array($_POST['status'] ?? '', ['published', 'draft', 'scheduled'], true) ? $_POST['status'] : 'draft';

    if ($title === '') { $errors[] = 'Title is required.'; }

    if (!$errors) {
        $slug = tp_unique_slug('blog_posts', $slugInput !== '' ? $slugInput : $title, $id ?: null);
        $oldSlug = $post['slug'] ?? null;

        if ($post) {
            tp_execute(
                'UPDATE blog_posts SET blog_category_id=?, title=?, slug=?, excerpt=?, content=?, featured_image=?, status=? WHERE id=?',
                'issssssi',
                [$categoryId, $title, $slug, $excerpt, $contentHtml, $featuredImage, $status, $id]
            );
        } else {
            $result = tp_execute(
                'INSERT INTO blog_posts (blog_category_id, admin_id, title, slug, excerpt, content, featured_image, status) VALUES (?,?,?,?,?,?,?,?)',
                'iissssss',
                [$categoryId, $_SESSION['admin_id'], $title, $slug, $excerpt, $contentHtml, $featuredImage, $status]
            );
            $id = $result['insert_id'];
        }

        tp_execute('DELETE FROM blog_post_tools WHERE blog_post_id = ?', 'i', [$id]);
        foreach ($_POST['related_tools'] ?? [] as $toolId) {
            tp_execute('INSERT IGNORE INTO blog_post_tools (blog_post_id, tool_id) VALUES (?, ?)', 'ii', [$id, (int) $toolId]);
        }

        save_seo_settings('blog_post', $id, [
            'seo_title' => tp_sanitize_text($_POST['seo_title'] ?? '', 160),
            'meta_description' => tp_sanitize_text($_POST['meta_description'] ?? '', 320),
            'focus_keyword' => tp_sanitize_text($_POST['focus_keyword'] ?? '', 150),
        ]);

        if ($oldSlug && $oldSlug !== $slug) {
            tp_delete_route_file($oldSlug, 'blog');
            tp_execute('INSERT INTO redirects (old_url, new_url, redirect_type) VALUES (?, ?, 301)', 'ss', ['/blog/' . $oldSlug, '/blog/' . $slug]);
        }
        tp_write_blog_route($slug);

        tp_log_activity($_SESSION['admin_id'], $post ? 'update_post' : 'create_post', 'blog_post', $id, $title);
        tp_flash_set('success', 'Post saved.');
        header('Location: ' . tp_url('admin/blog.php'));
        exit;
    }
}

$blogCategories = tp_query('SELECT * FROM blog_categories ORDER BY name');
$allTools = tp_query('SELECT id, name FROM tools ORDER BY name');
$adminPageTitle = $post ? 'Edit Post' : 'Add New Post';
require __DIR__ . '/includes/admin-header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post">
  <?= tp_csrf_field() ?>
  <div class="admin-card mb-3">
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Title</label><input type="text" name="title" class="form-control" data-slug-source value="<?= e($post['title'] ?? '') ?>" required></div>
      <div class="col-md-4"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" data-slug-target data-existing="<?= $post ? 1 : 0 ?>" data-original="<?= e($post['slug'] ?? '') ?>" value="<?= e($post['slug'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Blog Category</label>
        <select name="blog_category_id" class="form-select">
          <option value="0">— None —</option>
          <?php foreach ($blogCategories as $bc): ?>
            <option value="<?= (int) $bc['id'] ?>" <?= (int) ($post['blog_category_id'] ?? 0) === (int) $bc['id'] ? 'selected' : '' ?>><?= e($bc['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Featured Image URL</label><input type="text" name="featured_image" class="form-control" value="<?= e($post['featured_image'] ?? '') ?>"></div>
      <div class="col-md-12"><label class="form-label">Excerpt</label><textarea name="excerpt" class="form-control" rows="2"><?= e($post['excerpt'] ?? '') ?></textarea></div>
      <div class="col-md-12"><label class="form-label">Content</label><textarea name="content" id="blogContentEditor" rows="14"><?= $post['content'] ?? '' ?></textarea></div>
      <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['published'=>'Published','draft'=>'Draft','scheduled'=>'Scheduled'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($post['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-8"><label class="form-label">Related Tools</label>
        <select name="related_tools[]" class="form-select tp-select2" multiple>
          <?php foreach ($allTools as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $linkedToolIds, true) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
  </div>
  <div class="admin-card mb-3">
    <h3 class="h6 fw-bold">SEO</h3>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">SEO Title</label><input type="text" name="seo_title" class="form-control" value="<?= e($seo['seo_title'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Focus Keyword</label><input type="text" name="focus_keyword" class="form-control" value="<?= e($seo['focus_keyword'] ?? '') ?>"></div>
      <div class="col-md-12"><label class="form-label">Meta Description</label><textarea name="meta_description" class="form-control" rows="2"><?= e($seo['meta_description'] ?? '') ?></textarea></div>
    </div>
  </div>
  <button class="btn tp-btn-calc" style="width:auto;">Save Post</button>
</form>
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>CKEDITOR.replace('blogContentEditor');</script>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
