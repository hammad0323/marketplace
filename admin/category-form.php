<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$id = (int) ($_GET['id'] ?? 0);
$category = $id ? db_select_one($conn, 'SELECT * FROM categories WHERE id = ?', [$id]) : null;
if ($id && !$category) {
    flash_set('danger', 'Category not found.');
    redirect('/admin/categories.php');
}

$errors = [];

// ---- Handle field sub-form (add/delete a dynamic category field) -------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'field') {
    verify_csrf();
    if (!$category) {
        flash_set('danger', 'Category not found.');
        redirect('/admin/categories.php');
    }
    if (($_POST['field_action'] ?? '') === 'add') {
        $label = clean_input($_POST['field_label'] ?? '');
        $type = clean_input($_POST['field_type'] ?? 'text');
        if ($label !== '' && $category) {
            db_execute(
                $conn,
                'INSERT INTO category_fields (category_id, field_label, field_key, field_type, field_options, is_required, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, (SELECT m FROM (SELECT COALESCE(MAX(sort_order),0)+1 AS m FROM category_fields WHERE category_id = ?) t))',
                [
                    (int) $category['id'],
                    $label,
                    slugify($label),
                    in_array($type, ['text', 'number', 'textarea', 'select', 'checkbox', 'date', 'time'], true) ? $type : 'text',
                    clean_input($_POST['field_options'] ?? '') ?: null,
                    !empty($_POST['field_required']) ? 1 : 0,
                    (int) $category['id'],
                ]
            );
            flash_set('success', 'Field added.');
        }
    } elseif (($_POST['field_action'] ?? '') === 'delete') {
        db_execute($conn, 'DELETE FROM category_fields WHERE id = ? AND category_id = ?', [(int) $_POST['field_id'], (int) $category['id']]);
        flash_set('success', 'Field removed.');
    }
    redirect('/admin/category-form.php?id=' . (int) $category['id']);
}

// ---- Handle main category form ------------------------------------------
$old = [
    'name' => $category['name'] ?? '',
    'parent_id' => $category['parent_id'] ?? '',
    'icon' => $category['icon'] ?? 'bi-tag',
    'description' => $category['description'] ?? '',
    'seo_title' => $category['seo_title'] ?? '',
    'seo_description' => $category['seo_description'] ?? '',
    'sort_order' => $category['sort_order'] ?? 0,
    'is_active' => $category['is_active'] ?? 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'category') {
    verify_csrf();
    foreach (['name', 'icon', 'description', 'seo_title', 'seo_description'] as $f) {
        $old[$f] = clean_input($_POST[$f] ?? '');
    }
    $old['parent_id'] = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : '';
    $old['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $old['is_active'] = !empty($_POST['is_active']) ? 1 : 0;

    require_field($old['name'], 'Name', $errors);
    if ($old['parent_id'] !== '' && $category && (int) $old['parent_id'] === (int) $category['id']) {
        $errors[] = 'A category cannot be its own parent.';
    }

    $imagePath = $category['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $upload = upload_file('image', 'categories');
        if (!$upload['ok'] && $upload['error']) {
            $errors[] = $upload['error'];
        } elseif ($upload['ok']) {
            $imagePath = $upload['path'];
        }
    }

    if (!$errors) {
        $slug = unique_slug($conn, 'categories', $old['name'], $category['id'] ?? null);
        if ($category) {
            db_execute(
                $conn,
                'UPDATE categories SET parent_id=?, name=?, slug=?, icon=?, image=?, description=?, seo_title=?, seo_description=?, sort_order=?, is_active=? WHERE id=?',
                [$old['parent_id'] ?: null, $old['name'], $slug, $old['icon'], $imagePath, $old['description'], $old['seo_title'], $old['seo_description'], $old['sort_order'], $old['is_active'], (int) $category['id']]
            );
            log_audit($conn, (int) $admin['id'], 'category', (int) $category['id'], 'update');
            flash_set('success', 'Category updated.');
            redirect('/admin/category-form.php?id=' . (int) $category['id']);
        } else {
            $newId = db_insert_get_id(
                $conn,
                'INSERT INTO categories (parent_id, name, slug, icon, image, description, seo_title, seo_description, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$old['parent_id'] ?: null, $old['name'], $slug, $old['icon'], $imagePath, $old['description'], $old['seo_title'], $old['seo_description'], $old['sort_order'], $old['is_active']]
            );
            log_audit($conn, (int) $admin['id'], 'category', $newId, 'create');
            flash_set('success', 'Category created. You can now add custom fields below.');
            redirect('/admin/category-form.php?id=' . $newId);
        }
    }
}

$parentOptions = db_select($conn, 'SELECT id, name FROM categories WHERE parent_id IS NULL' . ($category ? ' AND id != ?' : '') . ' ORDER BY sort_order', $category ? [(int) $category['id']] : []);
$fields = $category ? db_select($conn, 'SELECT * FROM category_fields WHERE category_id = ? ORDER BY sort_order', [(int) $category['id']]) : [];

$adminPageTitle = $category ? 'Edit Category' : 'New Category';
$adminActive = 'categories';
require __DIR__ . '/_layout_top.php';
?>

<a href="<?php echo url('/admin/categories.php'); ?>" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to categories</a>

<div class="panel" style="margin-top:16px;">
  <div class="panel-head"><h3><?php echo $category ? 'Edit category' : 'New category'; ?></h3></div>

  <?php foreach ($errors as $err): ?>
    <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
  <?php endforeach; ?>

  <form method="post" class="form-w" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="form" value="category">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>Name</label><input type="text" name="name" value="<?php echo e($old['name']); ?>" required></div>
      <div>
        <label>Parent category (optional)</label>
        <select name="parent_id">
          <option value="">None — top-level category</option>
          <?php foreach ($parentOptions as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo (string) $p['id'] === (string) $old['parent_id'] ? 'selected' : ''; ?>><?php echo e($p['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Icon (Bootstrap Icons class, e.g. bi-building)</label><input type="text" name="icon" value="<?php echo e($old['icon']); ?>"></div>
      <div><label>Sort order</label><input type="number" name="sort_order" value="<?php echo (int) $old['sort_order']; ?>"></div>
    </div>
    <label>Description</label>
    <textarea name="description" rows="3"><?php echo e($old['description']); ?></textarea>
    <label>Image</label>
    <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
    <?php if (!empty($category['image'])): ?><div class="form-hint">Current: <?php echo e($category['image']); ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>SEO title</label><input type="text" name="seo_title" value="<?php echo e($old['seo_title']); ?>"></div>
      <div><label>SEO description</label><input type="text" name="seo_description" value="<?php echo e($old['seo_description']); ?>"></div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;margin-top:16px;">
      <input type="checkbox" name="is_active" value="1" <?php echo $old['is_active'] ? 'checked' : ''; ?> style="width:auto;"> Visible on site
    </label>
    <button type="submit" class="btn-w btn-primary" style="margin-top:20px;"><?php echo $category ? 'Save changes' : 'Create category'; ?></button>
  </form>
</div>

<?php if ($category): ?>
<div class="panel">
  <div class="panel-head"><h3>Custom fields for <?php echo e($category['name']); ?></h3></div>
  <p class="form-hint" style="margin-bottom:16px;">These appear as extra fields when a provider adds a service in this category (e.g. Room Type for Hotels, Car Brand for Rent a Car).</p>

  <?php if ($fields): ?>
    <table class="table-w">
      <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($fields as $f): ?>
          <tr>
            <td><?php echo e($f['field_label']); ?></td>
            <td><code><?php echo e($f['field_key']); ?></code></td>
            <td><?php echo e($f['field_type']); ?></td>
            <td><?php echo $f['is_required'] ? '<i class="bi bi-check-circle-fill" style="color:var(--success);"></i>' : '—'; ?></td>
            <td style="text-align:right;">
              <form method="post" onsubmit="return confirm('Remove this field?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form" value="field">
                <input type="hidden" name="field_action" value="delete">
                <input type="hidden" name="field_id" value="<?php echo (int) $f['id']; ?>">
                <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" style="display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:12px;align-items:end;margin-top:18px;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="form" value="field">
    <input type="hidden" name="field_action" value="add">
    <div><label style="margin:0 0 6px;font-size:13px;font-weight:600;">Field label</label><input type="text" name="field_label" placeholder="e.g. Room Type" required style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <div>
      <label style="margin:0 0 6px;font-size:13px;font-weight:600;">Type</label>
      <select name="field_type" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);">
        <option value="text">Text</option>
        <option value="number">Number</option>
        <option value="textarea">Textarea</option>
        <option value="select">Select</option>
        <option value="checkbox">Checkbox</option>
        <option value="date">Date</option>
        <option value="time">Time</option>
      </select>
    </div>
    <div><label style="margin:0 0 6px;font-size:13px;font-weight:600;">Options (comma-separated, for Select)</label><input type="text" name="field_options" placeholder="Single,Double,Suite" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <button type="submit" class="btn-w btn-primary btn-sm">Add field</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
