<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);

if (!$provider || $provider['status'] !== 'approved') {
    flash_set('danger', 'Your business must be approved before you can add services.');
    redirect('/provider/index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$service = $id ? db_select_one($conn, 'SELECT * FROM services WHERE id = ? AND provider_id = ?', [$id, (int) $provider['id']]) : null;
if ($id && !$service) {
    flash_set('danger', 'Service not found.');
    redirect('/provider/services.php');
}

$categoryId = $service ? (int) $service['category_id'] : (int) ($_GET['category_id'] ?? 0);
$category = $categoryId ? db_select_one($conn, 'SELECT * FROM categories WHERE id = ? AND is_active = 1', [$categoryId]) : null;

// ---- Step 1: category picker (new service only, no category chosen yet) --
if (!$service && !$category) {
    $categories = db_select($conn, 'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
    $pageTitle = 'Choose a category';
    require ROOT_PATH . '/includes/header.php';
    ?>
    <div class="section-tight">
      <div class="container-xl" style="max-width:760px;">
        <div class="section-head">
          <span class="eyebrow"><i class="bi bi-plus-lg"></i> New service</span>
          <h1 class="section-heading">What are you listing?</h1>
          <p class="section-sub">Pick a category — the form will show the fields relevant to it.</p>
        </div>
        <div class="category-grid stagger reveal in-view">
          <?php foreach ($categories as $cat): ?>
            <a class="category-card" href="/provider/service-form.php?category_id=<?php echo (int) $cat['id']; ?>">
              <div class="icon-wrap"><i class="bi <?php echo e($cat['icon'] ?: 'bi-tag'); ?>"></i></div>
              <div class="name"><?php echo e($cat['name']); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php require ROOT_PATH . '/includes/footer.php'; ?>
    <?php
    exit;
}

if (!$category) {
    flash_set('danger', 'Please choose a valid category.');
    redirect('/provider/service-form.php');
}

$errors = [];
$cities = db_select($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order');
$amenities = db_select($conn, 'SELECT * FROM amenities ORDER BY name');
$categoryFields = db_select($conn, 'SELECT * FROM category_fields WHERE category_id = ? ORDER BY sort_order', [(int) $category['id']]);
$existingAmenityIds = $service ? array_column(db_select($conn, 'SELECT amenity_id FROM service_amenity_map WHERE service_id = ?', [(int) $service['id']]), 'amenity_id') : [];
$existingFieldValues = [];
if ($service) {
    foreach (db_select($conn, 'SELECT category_field_id, field_value FROM service_field_values WHERE service_id = ?', [(int) $service['id']]) as $row) {
        $existingFieldValues[$row['category_field_id']] = $row['field_value'];
    }
}
$existingImages = $service ? db_select($conn, 'SELECT * FROM service_images WHERE service_id = ? ORDER BY is_cover DESC, sort_order', [(int) $service['id']]) : [];

$old = [
    'title' => $service['title'] ?? '',
    'city_id' => $service['city_id'] ?? '',
    'short_description' => $service['short_description'] ?? '',
    'description' => $service['description'] ?? '',
    'address' => $service['address'] ?? '',
    'latitude' => $service['latitude'] ?? '',
    'longitude' => $service['longitude'] ?? '',
    'price' => $service['price'] ?? '',
    'price_unit' => $service['price_unit'] ?? 'fixed',
    'max_guests' => $service['max_guests'] ?? '',
    'cancellation_policy' => $service['cancellation_policy'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title', 'short_description', 'description', 'address', 'cancellation_policy', 'price_unit'] as $f) {
        $old[$f] = clean_input($_POST[$f] ?? '');
    }
    $old['city_id'] = (int) ($_POST['city_id'] ?? 0) ?: '';
    $old['latitude'] = clean_input($_POST['latitude'] ?? '');
    $old['longitude'] = clean_input($_POST['longitude'] ?? '');
    $old['price'] = (float) ($_POST['price'] ?? 0);
    $old['max_guests'] = isset($_POST['max_guests']) && $_POST['max_guests'] !== '' ? (int) $_POST['max_guests'] : '';

    require_field($old['title'], 'Title', $errors);
    if ($old['price'] <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    if (!in_array($old['price_unit'], ['hour', 'day', 'night', 'person', 'fixed'], true)) {
        $errors[] = 'Invalid pricing unit.';
    }

    if (!$errors) {
        $slug = unique_slug($conn, 'services', $old['title'], $service['id'] ?? null);
        $params = [
            (int) $category['id'], $old['city_id'] ?: null, $old['title'], $slug, $old['short_description'], $old['description'],
            $old['address'], $old['latitude'] ?: null, $old['longitude'] ?: null, $old['price'], $old['price_unit'],
            $old['max_guests'] ?: null, $old['cancellation_policy'],
        ];

        if ($service) {
            db_execute(
                $conn,
                'UPDATE services SET category_id=?, city_id=?, title=?, slug=?, short_description=?, description=?, address=?, latitude=?, longitude=?, price=?, price_unit=?, max_guests=?, cancellation_policy=?, status="pending" WHERE id=?',
                array_merge($params, [(int) $service['id']])
            );
            $serviceId = (int) $service['id'];
            flash_set('success', 'Service updated and resubmitted for approval.');
        } else {
            $serviceId = db_insert_get_id(
                $conn,
                'INSERT INTO services (provider_id, category_id, city_id, title, slug, short_description, description, address, latitude, longitude, price, price_unit, max_guests, cancellation_policy, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, "pending")',
                array_merge([(int) $provider['id']], $params)
            );
            flash_set('success', 'Service submitted for approval.');
        }

        // Amenities
        db_execute($conn, 'DELETE FROM service_amenity_map WHERE service_id = ?', [$serviceId]);
        foreach ((array) ($_POST['amenities'] ?? []) as $amenityId) {
            db_execute($conn, 'INSERT IGNORE INTO service_amenity_map (service_id, amenity_id) VALUES (?, ?)', [$serviceId, (int) $amenityId]);
        }

        // Dynamic category fields
        db_execute($conn, 'DELETE FROM service_field_values WHERE service_id = ?', [$serviceId]);
        foreach ($categoryFields as $field) {
            $key = 'field_' . $field['id'];
            $value = $field['field_type'] === 'checkbox' ? (!empty($_POST[$key]) ? '1' : '0') : clean_input($_POST[$key] ?? '');
            if ($value !== '') {
                db_execute($conn, 'INSERT INTO service_field_values (service_id, category_field_id, field_value) VALUES (?, ?, ?)', [$serviceId, (int) $field['id'], $value]);
            }
        }

        // Image uploads (append)
        if (!empty($_FILES['images']['name'][0])) {
            $existingCount = db_count($conn, 'SELECT COUNT(*) FROM service_images WHERE service_id = ?', [$serviceId]);
            foreach ($_FILES['images']['name'] as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $singleFile = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];
                $_FILES['__single_image'] = $singleFile;
                $upload = upload_file('__single_image', 'services');
                if ($upload['ok']) {
                    db_execute(
                        $conn,
                        'INSERT INTO service_images (service_id, image_path, is_cover, sort_order) VALUES (?, ?, ?, ?)',
                        [$serviceId, $upload['path'], $existingCount === 0 && $i === 0 ? 1 : 0, $existingCount + $i]
                    );
                }
            }
        }
        // Remove images the provider checked for deletion
        foreach ((array) ($_POST['delete_images'] ?? []) as $imgId) {
            db_execute($conn, 'DELETE FROM service_images WHERE id = ? AND service_id = ?', [(int) $imgId, $serviceId]);
        }

        redirect('/provider/services.php');
    }
}

$pageTitle = $service ? 'Edit Service' : 'New Service';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:820px;">
    <a href="/provider/services.php" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to services</a>

    <div class="section-head" style="margin-top:16px;">
      <span class="eyebrow"><i class="bi <?php echo e($category['icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($category['name']); ?></span>
      <h1 class="section-heading"><?php echo $service ? 'Edit service' : 'New service'; ?></h1>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-w panel" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo e($old['title']); ?>" required>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <div>
          <label>City</label>
          <select name="city_id">
            <option value="">Select city</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>" <?php echo (string) $c['id'] === (string) $old['city_id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Address</label><input type="text" name="address" value="<?php echo e($old['address']); ?>"></div>
        <div><label>Latitude (optional)</label><input type="text" name="latitude" value="<?php echo e($old['latitude']); ?>" placeholder="24.8607"></div>
        <div><label>Longitude (optional)</label><input type="text" name="longitude" value="<?php echo e($old['longitude']); ?>" placeholder="67.0011"></div>
      </div>

      <label>Short description (shown on cards)</label>
      <input type="text" name="short_description" maxlength="300" value="<?php echo e($old['short_description']); ?>">
      <label>Full description</label>
      <textarea name="description" rows="5"><?php echo e($old['description']); ?></textarea>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 20px;">
        <div><label>Price</label><input type="number" step="0.01" min="0.01" name="price" value="<?php echo e($old['price']); ?>" required></div>
        <div>
          <label>Per</label>
          <select name="price_unit">
            <?php foreach (['fixed' => 'Fixed', 'hour' => 'Hour', 'day' => 'Day', 'night' => 'Night', 'person' => 'Person'] as $val => $label): ?>
              <option value="<?php echo $val; ?>" <?php echo $old['price_unit'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Max guests (optional)</label><input type="number" min="1" name="max_guests" value="<?php echo e($old['max_guests']); ?>"></div>
      </div>

      <?php if ($categoryFields): ?>
        <h3 style="font-size:15px;margin:26px 0 4px;">Category details</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
          <?php foreach ($categoryFields as $field): ?>
            <?php $fval = $existingFieldValues[$field['id']] ?? ''; $fkey = 'field_' . $field['id']; ?>
            <div>
              <label><?php echo e($field['field_label']); ?><?php echo $field['is_required'] ? ' *' : ''; ?></label>
              <?php if ($field['field_type'] === 'select'): ?>
                <select name="<?php echo $fkey; ?>" <?php echo $field['is_required'] ? 'required' : ''; ?>>
                  <option value="">Select…</option>
                  <?php foreach (explode(',', (string) $field['field_options']) as $opt): $opt = trim($opt); ?>
                    <option value="<?php echo e($opt); ?>" <?php echo $fval === $opt ? 'selected' : ''; ?>><?php echo e($opt); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($field['field_type'] === 'checkbox'): ?>
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="<?php echo $fkey; ?>" value="1" <?php echo $fval === '1' ? 'checked' : ''; ?> style="width:auto;"> Yes</label>
              <?php elseif ($field['field_type'] === 'textarea'): ?>
                <textarea name="<?php echo $fkey; ?>" rows="2"><?php echo e($fval); ?></textarea>
              <?php else: ?>
                <input type="<?php echo e($field['field_type']); ?>" name="<?php echo $fkey; ?>" value="<?php echo e($fval); ?>" <?php echo $field['is_required'] ? 'required' : ''; ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($amenities): ?>
        <h3 style="font-size:15px;margin:26px 0 10px;">Amenities</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php foreach ($amenities as $a): ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:500;font-size:14px;">
              <input type="checkbox" name="amenities[]" value="<?php echo (int) $a['id']; ?>" <?php echo in_array($a['id'], $existingAmenityIds, false) ? 'checked' : ''; ?> style="width:auto;">
              <i class="bi <?php echo e($a['icon'] ?: 'bi-check'); ?>"></i> <?php echo e($a['name']); ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3 style="font-size:15px;margin:26px 0 10px;">Photos</h3>
      <?php if ($existingImages): ?>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;">
          <?php foreach ($existingImages as $img): ?>
            <div style="position:relative;">
              <img src="<?php echo e($img['image_path']); ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;">
              <label style="position:absolute;top:4px;right:4px;background:#fff;border-radius:6px;padding:2px 4px;font-size:11px;display:flex;align-items:center;gap:3px;">
                <input type="checkbox" name="delete_images[]" value="<?php echo (int) $img['id']; ?>" style="width:auto;"> del
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
      <div class="form-hint">First photo becomes the cover image. Add more any time.</div>

      <label style="margin-top:20px;">Cancellation policy</label>
      <textarea name="cancellation_policy" rows="3"><?php echo e($old['cancellation_policy']); ?></textarea>

      <button type="submit" class="btn-w btn-primary" style="margin-top:24px;"><?php echo $service ? 'Save & resubmit for approval' : 'Submit for approval'; ?></button>
    </form>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
