<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$category = $id ? get_category($id) : null;
$seo = $id ? (get_seo_settings('category', $id) ?? []) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();

    $name = tp_sanitize_text($_POST['name'] ?? '', 150);
    $slugInput = tp_sanitize_text($_POST['slug'] ?? '', 170);
    $description = tp_sanitize_text($_POST['description'] ?? '', 2000);
    $icon = tp_sanitize_text($_POST['icon'] ?? 'bi-grid', 80);
    $color = tp_sanitize_text($_POST['color'] ?? '#6366F1', 20);
    $status = in_array($_POST['status'] ?? '', ['published', 'draft', 'hidden'], true) ? $_POST['status'] : 'published';
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }

    if (!$errors) {
        $slug = tp_unique_slug('categories', $slugInput !== '' ? $slugInput : $name, $id ?: null);
        $oldSlug = $category['slug'] ?? null;

        if ($category) {
            tp_execute(
                'UPDATE categories SET name=?, slug=?, description=?, icon=?, color=?, status=?, sort_order=? WHERE id=?',
                'sssssiii',
                [$name, $slug, $description, $icon, $color, $status, $sortOrder, $id]
            );
        } else {
            $result = tp_execute(
                'INSERT INTO categories (name, slug, description, icon, color, status, sort_order) VALUES (?,?,?,?,?,?,?)',
                'ssssssi',
                [$name, $slug, $description, $icon, $color, $status, $sortOrder]
            );
            $id = $result['insert_id'];
        }

        save_seo_settings('category', $id, [
            'seo_title' => tp_sanitize_text($_POST['seo_title'] ?? '', 160),
            'meta_description' => tp_sanitize_text($_POST['meta_description'] ?? '', 320),
            'focus_keyword' => tp_sanitize_text($_POST['focus_keyword'] ?? '', 150),
            'canonical_url' => tp_sanitize_text($_POST['canonical_url'] ?? '', 255),
            'og_title' => tp_sanitize_text($_POST['og_title'] ?? '', 160),
            'og_description' => tp_sanitize_text($_POST['og_description'] ?? '', 320),
            'robots' => tp_sanitize_text($_POST['robots'] ?? 'index,follow', 60),
        ]);

        if ($oldSlug && $oldSlug !== $slug) {
            tp_delete_route_file($oldSlug);
            tp_execute(
                "INSERT INTO redirects (old_url, new_url, redirect_type) VALUES (?, ?, 301)",
                'ss',
                ['/' . $oldSlug, '/' . $slug]
            );
        }
        tp_write_category_route($slug);

        tp_log_activity($_SESSION['admin_id'], $category ? 'update_category' : 'create_category', 'category', $id, $name);
        tp_flash_set('success', 'Category saved successfully.');
        header('Location: ' . tp_url('admin/categories.php'));
        exit;
    }
}

$adminPageTitle = $category ? 'Edit Category' : 'Add New Category';
require __DIR__ . '/includes/admin-header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" id="tpCategoryForm">
  <?= tp_csrf_field() ?>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Basic Info</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-seo">SEO</a></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-basic">
      <div class="admin-card">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" data-slug-source value="<?= e($category['name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Slug (URL: /slug.php)</label>
            <input type="text" name="slug" class="form-control" data-slug-target data-existing="<?= $category ? 1 : 0 ?>" data-original="<?= e($category['slug'] ?? '') ?>" value="<?= e($category['slug'] ?? '') ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= e($category['description'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Icon (Bootstrap Icon class)</label>
            <input type="text" name="icon" class="form-control" value="<?= e($category['icon'] ?? 'bi-grid') ?>" placeholder="bi-briefcase">
          </div>
          <div class="col-md-4">
            <label class="form-label">Color</label>
            <input type="color" name="color" class="form-control form-control-color" value="<?= e($category['color'] ?? '#6366F1') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int) ($category['sort_order'] ?? 0) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'hidden' => 'Hidden'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($category['status'] ?? 'published') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="tab-pane fade" id="tab-seo">
      <div class="admin-card">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">SEO Title</label>
            <input type="text" name="seo_title" class="form-control" data-counter-seo-title value="<?= e($seo['seo_title'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Focus Keyword</label>
            <input type="text" name="focus_keyword" class="form-control" value="<?= e($seo['focus_keyword'] ?? '') ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Meta Description</label>
            <textarea name="meta_description" class="form-control" data-counter-meta-description rows="2"><?= e($seo['meta_description'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6"><label class="form-label">Canonical URL</label><input type="text" name="canonical_url" class="form-control" value="<?= e($seo['canonical_url'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Robots</label><input type="text" name="robots" class="form-control" value="<?= e($seo['robots'] ?? 'index,follow') ?>"></div>
          <div class="col-md-6"><label class="form-label">OG Title</label><input type="text" name="og_title" class="form-control" value="<?= e($seo['og_title'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">OG Description</label><input type="text" name="og_description" class="form-control" value="<?= e($seo['og_description'] ?? '') ?>"></div>
        </div>
      </div>
    </div>
  </div>
  <button class="btn tp-btn-calc mt-3" style="width:auto;">Save Category</button>
</form>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
