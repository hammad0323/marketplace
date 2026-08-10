<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$id = (int) ($_GET['id'] ?? 0);
$city = $id ? db_select_one($conn, 'SELECT * FROM cities WHERE id = ?', [$id]) : null;
if ($id && !$city) {
    flash_set('danger', 'City not found.');
    redirect('/admin/cities.php');
}

$errors = [];
$old = [
    'name' => $city['name'] ?? '',
    'country_id' => $city['country_id'] ?? '',
    'description' => $city['description'] ?? '',
    'latitude' => $city['latitude'] ?? '',
    'longitude' => $city['longitude'] ?? '',
    'seo_title' => $city['seo_title'] ?? '',
    'seo_description' => $city['seo_description'] ?? '',
    'sort_order' => $city['sort_order'] ?? 0,
    'is_featured' => $city['is_featured'] ?? 0,
    'is_active' => $city['is_active'] ?? 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['name', 'description', 'seo_title', 'seo_description'] as $f) {
        $old[$f] = clean_input($_POST[$f] ?? '');
    }
    $old['country_id'] = (int) ($_POST['country_id'] ?? 0);
    $old['latitude'] = clean_input($_POST['latitude'] ?? '');
    $old['longitude'] = clean_input($_POST['longitude'] ?? '');
    $old['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $old['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $old['is_active'] = !empty($_POST['is_active']) ? 1 : 0;

    require_field($old['name'], 'City name', $errors);
    if (!$old['country_id']) {
        $errors[] = 'Country is required.';
    }

    $imagePath = $city['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $upload = upload_file('image', 'cities');
        if (!$upload['ok'] && $upload['error']) {
            $errors[] = $upload['error'];
        } elseif ($upload['ok']) {
            $imagePath = $upload['path'];
        }
    }

    if (!$errors) {
        $slug = unique_slug($conn, 'cities', $old['name'], $city['id'] ?? null);
        $params = [
            $old['country_id'], $old['name'], $slug, $imagePath, $old['description'],
            $old['latitude'] ?: null, $old['longitude'] ?: null,
            $old['is_featured'], $old['is_active'], $old['sort_order'], $old['seo_title'], $old['seo_description'],
        ];
        if ($city) {
            db_execute(
                $conn,
                'UPDATE cities SET country_id=?, name=?, slug=?, image=?, description=?, latitude=?, longitude=?, is_featured=?, is_active=?, sort_order=?, seo_title=?, seo_description=? WHERE id=?',
                array_merge($params, [(int) $city['id']])
            );
            log_audit($conn, (int) $admin['id'], 'city', (int) $city['id'], 'update');
            flash_set('success', 'City updated.');
        } else {
            $newId = db_insert_get_id(
                $conn,
                'INSERT INTO cities (country_id, name, slug, image, description, latitude, longitude, is_featured, is_active, sort_order, seo_title, seo_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                $params
            );
            log_audit($conn, (int) $admin['id'], 'city', $newId, 'create');
            flash_set('success', 'City created.');
        }
        redirect('/admin/cities.php');
    }
}

$countries = db_select($conn, 'SELECT id, name FROM countries ORDER BY name');

$adminPageTitle = $city ? 'Edit City' : 'New City';
$adminActive = 'cities';
require __DIR__ . '/_layout_top.php';
?>

<a href="/admin/cities.php" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to cities</a>

<div class="panel" style="margin-top:16px;max-width:720px;">
  <div class="panel-head"><h3><?php echo $city ? 'Edit city' : 'New city'; ?></h3></div>

  <?php foreach ($errors as $err): ?>
    <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
  <?php endforeach; ?>

  <form method="post" class="form-w" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>City name</label><input type="text" name="name" value="<?php echo e($old['name']); ?>" required></div>
      <div>
        <label>Country</label>
        <select name="country_id" required>
          <option value="">Select country</option>
          <?php foreach ($countries as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>" <?php echo (string) $c['id'] === (string) $old['country_id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Latitude</label><input type="text" name="latitude" value="<?php echo e($old['latitude']); ?>" placeholder="24.8607"></div>
      <div><label>Longitude</label><input type="text" name="longitude" value="<?php echo e($old['longitude']); ?>" placeholder="67.0011"></div>
    </div>
    <label>Description</label>
    <textarea name="description" rows="3"><?php echo e($old['description']); ?></textarea>
    <label>Image</label>
    <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
    <?php if (!empty($city['image'])): ?><div class="form-hint">Current: <?php echo e($city['image']); ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
      <div><label>SEO title</label><input type="text" name="seo_title" value="<?php echo e($old['seo_title']); ?>"></div>
      <div><label>SEO description</label><input type="text" name="seo_description" value="<?php echo e($old['seo_description']); ?>"></div>
    </div>
    <label>Sort order</label>
    <input type="number" name="sort_order" value="<?php echo (int) $old['sort_order']; ?>" style="max-width:140px;">
    <div style="display:flex;gap:24px;margin-top:16px;">
      <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" value="1" <?php echo $old['is_active'] ? 'checked' : ''; ?> style="width:auto;"> Visible on site</label>
      <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_featured" value="1" <?php echo $old['is_featured'] ? 'checked' : ''; ?> style="width:auto;"> Featured on homepage</label>
    </div>
    <button type="submit" class="btn-w btn-primary" style="margin-top:20px;"><?php echo $city ? 'Save changes' : 'Create city'; ?></button>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
