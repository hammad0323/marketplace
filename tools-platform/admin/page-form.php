<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$page = $id ? tp_query_one('SELECT * FROM pages WHERE id = ?', 'i', [$id]) : null;
$seo = $id ? (get_seo_settings('page', $id) ?? []) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $title = tp_sanitize_text($_POST['title'] ?? '', 200);
    $slugInput = tp_sanitize_text($_POST['slug'] ?? '', 220);
    $contentHtml = $_POST['content'] ?? ''; // CKEditor HTML — admin-only, trusted input
    $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'draft';

    if ($title === '') { $errors[] = 'Title is required.'; }

    if (!$errors) {
        $slug = tp_unique_slug('pages', $slugInput !== '' ? $slugInput : $title, $id ?: null);
        $oldSlug = $page['slug'] ?? null;

        if ($page) {
            tp_execute('UPDATE pages SET title=?, slug=?, content=?, status=? WHERE id=?', 'ssssi', [$title, $slug, $contentHtml, $status, $id]);
        } else {
            $result = tp_execute('INSERT INTO pages (title, slug, content, status) VALUES (?,?,?,?)', 'ssss', [$title, $slug, $contentHtml, $status]);
            $id = $result['insert_id'];
        }

        save_seo_settings('page', $id, [
            'seo_title' => tp_sanitize_text($_POST['seo_title'] ?? '', 160),
            'meta_description' => tp_sanitize_text($_POST['meta_description'] ?? '', 320),
            'focus_keyword' => tp_sanitize_text($_POST['focus_keyword'] ?? '', 150),
        ]);

        if ($oldSlug && $oldSlug !== $slug) {
            tp_delete_route_file($oldSlug);
            tp_execute('INSERT INTO redirects (old_url, new_url, redirect_type) VALUES (?, ?, 301)', 'ss', ['/' . $oldSlug . '.php', '/' . $slug . '.php']);
        }
        tp_write_page_route($slug);

        tp_log_activity($_SESSION['admin_id'], $page ? 'update_page' : 'create_page', 'page', $id, $title);
        tp_flash_set('success', 'Page saved.');
        header('Location: ' . tp_url('admin/pages.php'));
        exit;
    }
}

$adminPageTitle = $page ? 'Edit Page' : 'Add New Page';
require __DIR__ . '/includes/admin-header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" id="tpPageForm">
  <?= tp_csrf_field() ?>
  <div class="admin-card mb-3">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" data-slug-source value="<?= e($page['title'] ?? '') ?>" required <?= !empty($page['is_system']) ? '' : '' ?>></div>
      <div class="col-md-6"><label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" data-slug-target data-existing="<?= $page ? 1 : 0 ?>" data-original="<?= e($page['slug'] ?? '') ?>" value="<?= e($page['slug'] ?? '') ?>" <?= !empty($page['is_system']) ? 'readonly' : '' ?>></div>
      <div class="col-md-12"><label class="form-label">Content</label>
        <textarea name="content" id="pageContentEditor" rows="14"><?= $page['content'] ?? '' ?></textarea></div>
      <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="published" <?= ($page['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
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
  <button class="btn tp-btn-calc" style="width:auto;">Save Page</button>
</form>
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>CKEDITOR.replace('pageContentEditor');</script>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
