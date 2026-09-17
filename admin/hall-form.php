<?php
require __DIR__ . '/../config.php';
wh_require_page_access('hall-form');
$businessId = wh_current_business_id();

$id = (int) wh_input_get('id', 0);
$hall = $id ? wh_get_hall($id, $businessId) : null;
if ($id && !$hall) {
    wh_flash_set('error', 'Hall not found.');
    wh_redirect(BASE_URL . '/admin/halls.php');
}
$facilities = $id ? wh_hall_facilities($id) : [];
$images = $id ? wh_hall_images($id) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $name = wh_input_post('name');
    $description = wh_input_post('description');
    $capacityMin = (int) wh_input_post('capacity_min');
    $capacityMax = (int) wh_input_post('capacity_max');
    $priceType = wh_input_post('price_type') === 'per_person' ? 'per_person' : 'fixed';
    $basePrice = wh_decimal(wh_input_post('base_price', 0));
    $perPersonPrice = wh_decimal(wh_input_post('per_person_price', 0));
    $address = wh_input_post('address');
    $city = wh_input_post('city');
    $area = wh_input_post('area');
    $mapUrl = wh_input_post('map_url');
    $mapEmbed = wh_input_post('map_embed');
    $status = wh_input_post('status') === 'inactive' ? 'inactive' : 'active';
    $isPublic = wh_input_post('is_public') === '1' ? 1 : 0;
    $isBookingEnabled = wh_input_post('is_booking_enabled') === '1' ? 1 : 0;
    $seoTitle = wh_input_post('seo_title');
    $metaDescription = wh_input_post('meta_description');

    if ($name === '') $errors[] = 'Hall name is required.';

    if (!$errors) {
        $slug = wh_unique_hall_slug($name, $businessId, $id);
        $uploadedFeatured = wh_handle_image_upload('featured_image', 'halls', 'hall');
        if ($uploadedFeatured === false) $errors[] = 'Featured image upload failed (max 3MB, JPG/PNG/WEBP only).';

        if (!$errors) {
            if ($id) {
                $sql = 'UPDATE halls SET name=?,slug=?,description=?,capacity_min=?,capacity_max=?,price_type=?,base_price=?,per_person_price=?,
                        address=?,city=?,area=?,map_url=?,map_embed=?,status=?,is_public=?,is_booking_enabled=?,seo_title=?,meta_description=?'
                    . ($uploadedFeatured ? ',featured_image=?' : '') . ' WHERE id=? AND business_id=?';
                $types = 'sssiisdd' . 'sssss' . 'siiss';
                $params = [$name, $slug, $description, $capacityMin, $capacityMax, $priceType, $basePrice, $perPersonPrice,
                    $address, $city, $area, $mapUrl, $mapEmbed, $status, $isPublic, $isBookingEnabled, $seoTitle, $metaDescription];
                if ($uploadedFeatured) { $types .= 's'; $params[] = $uploadedFeatured; }
                $types .= 'ii';
                $params[] = $id;
                $params[] = $businessId;
                wh_execute($sql, $types, $params);
                wh_execute('DELETE FROM hall_facilities WHERE hall_id=?', 'i', [$id]);
                wh_flash_set('success', 'Hall updated.');
            } else {
                $id = wh_execute(
                    'INSERT INTO halls (business_id,name,slug,description,capacity_min,capacity_max,price_type,base_price,per_person_price,
                        address,city,area,map_url,map_embed,featured_image,status,is_public,is_booking_enabled,seo_title,meta_description)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    'isssiisdd' . 'sssss' . 'ssiiss',
                    [$businessId, $name, $slug, $description, $capacityMin, $capacityMax, $priceType, $basePrice, $perPersonPrice,
                        $address, $city, $area, $mapUrl, $mapEmbed, $uploadedFeatured ?: null, $status, $isPublic, $isBookingEnabled, $seoTitle, $metaDescription]
                );
                wh_flash_set('success', 'Hall created.');
            }

            $facNames = $_POST['facility_name'] ?? [];
            $facIcons = $_POST['facility_icon'] ?? [];
            foreach ($facNames as $i => $fn) {
                $fn = trim($fn);
                if ($fn === '') continue;
                wh_execute('INSERT INTO hall_facilities (hall_id, facility_name, icon, sort_order) VALUES (?,?,?,?)',
                    'issi', [$id, $fn, $facIcons[$i] ?? 'fa-circle-check', $i]);
            }

            if (!empty($_FILES['gallery_images']['name'][0])) {
                foreach ($_FILES['gallery_images']['name'] as $idx => $fname) {
                    if ($_FILES['gallery_images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                    $tmpFile = ['name' => $fname, 'type' => $_FILES['gallery_images']['type'][$idx],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$idx], 'error' => $_FILES['gallery_images']['error'][$idx],
                        'size' => $_FILES['gallery_images']['size'][$idx]];
                    $_FILES['__single'] = $tmpFile;
                    $path = wh_handle_image_upload('__single', 'halls', 'hall-gallery');
                    if ($path) {
                        wh_execute('INSERT INTO hall_images (hall_id, image_path, sort_order) VALUES (?,?,0)', 'is', [$id, $path]);
                    }
                }
            }

            wh_redirect(BASE_URL . '/admin/hall-form.php?id=' . $id);
        }
    }
}

$pageTitle = $id ? 'Edit Hall' : 'Add Hall';
$activePage = 'hall-form';
require __DIR__ . '/header.php';
?>
<form method="post" enctype="multipart/form-data">
  <?= wh_csrf_field() ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Hall Information</h3>
    <div class="form-grid">
      <div class="form-group"><label>Hall Name *</label><input type="text" name="name" value="<?= e($hall['name'] ?? '') ?>" required></div>
      <div class="form-group"><label>City</label><input type="text" name="city" value="<?= e($hall['city'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?= e($hall['description'] ?? '') ?></textarea></div>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Minimum Guests</label><input type="number" name="capacity_min" value="<?= e($hall['capacity_min'] ?? 0) ?>"></div>
      <div class="form-group"><label>Maximum Guests</label><input type="number" name="capacity_max" value="<?= e($hall['capacity_max'] ?? 0) ?>"></div>
      <div class="form-group"><label>Status</label>
        <select name="status"><option value="active" <?= ($hall['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($hall['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
      </div>
    </div>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Price Type</label>
        <select name="price_type" id="priceType"><option value="fixed" <?= ($hall['price_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Fixed Total</option><option value="per_person" <?= ($hall['price_type'] ?? '') === 'per_person' ? 'selected' : '' ?>>Per Person</option></select>
      </div>
      <div class="form-group"><label>Fixed Price (PKR)</label><input type="number" step="0.01" name="base_price" value="<?= e($hall['base_price'] ?? 0) ?>"></div>
      <div class="form-group"><label>Per Person Price (PKR)</label><input type="number" step="0.01" name="per_person_price" value="<?= e($hall['per_person_price'] ?? 0) ?>"></div>
    </div>
    <div class="form-grid cols-3">
      <div class="form-group"><label><input type="checkbox" name="is_public" value="1" <?= ($hall['is_public'] ?? 1) ? 'checked' : '' ?> style="width:auto;"> Show on Website</label></div>
      <div class="form-group"><label><input type="checkbox" name="is_booking_enabled" value="1" <?= ($hall['is_booking_enabled'] ?? 1) ? 'checked' : '' ?> style="width:auto;"> Allow Online Booking</label></div>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Location</h3>
    <div class="form-grid">
      <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= e($hall['address'] ?? '') ?>"></div>
      <div class="form-group"><label>Area</label><input type="text" name="area" value="<?= e($hall['area'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Google Maps URL</label><input type="text" name="map_url" value="<?= e($hall['map_url'] ?? '') ?>"></div>
    <div class="form-group"><label>Google Maps Embed Code (iframe)</label><textarea name="map_embed" rows="3"><?= e($hall['map_embed'] ?? '') ?></textarea></div>
  </div>

  <div class="admin-card">
    <div class="card-head"><h3>Facilities</h3><button type="button" class="btn btn-light btn-sm" id="addFacilityBtn"><i class="fa-solid fa-plus"></i> Add</button></div>
    <div id="facilityRows">
      <?php if (!$facilities) $facilities = [['facility_name' => '', 'icon' => 'fa-circle-check']]; ?>
      <?php foreach ($facilities as $f): ?>
      <div class="form-grid" style="grid-template-columns:1fr 1fr auto;align-items:end;">
        <div class="form-group"><label>Facility</label><input type="text" name="facility_name[]" value="<?= e($f['facility_name']) ?>" placeholder="e.g. Car Parking"></div>
        <div class="form-group"><label>Icon (Font Awesome class)</label><input type="text" name="facility_icon[]" value="<?= e($f['icon'] ?: 'fa-circle-check') ?>" placeholder="fa-square-parking"></div>
        <button type="button" class="btn btn-light btn-icon remove-row"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Images</h3>
    <div class="form-group"><label>Featured Image</label><input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp">
      <?php if (!empty($hall['featured_image'])): ?><img src="<?= e(BASE_URL . '/' . $hall['featured_image']) ?>" style="height:80px;border-radius:8px;margin-top:8px;"><?php endif; ?>
    </div>
    <div class="form-group"><label>Gallery Images (multiple)</label><input type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/webp"></div>
    <?php if ($images): ?>
    <div class="gallery-grid" style="margin-top:12px;">
      <?php foreach ($images as $img): ?><div class="gallery-item"><img src="<?= e(BASE_URL . '/' . $img['image_path']) ?>"></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">SEO</h3>
    <div class="form-group"><label>SEO Title</label><input type="text" name="seo_title" value="<?= e($hall['seo_title'] ?? '') ?>"></div>
    <div class="form-group"><label>Meta Description</label><textarea name="meta_description" rows="2"><?= e($hall['meta_description'] ?? '') ?></textarea></div>
  </div>

  <button type="submit" class="btn btn-primary">Save Hall</button>
  <a href="<?= e(BASE_URL) ?>/admin/halls.php" class="btn btn-light">Cancel</a>
</form>
<script>
document.getElementById('addFacilityBtn').addEventListener('click', function () {
  var row = document.querySelector('#facilityRows .form-grid').cloneNode(true);
  row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
  document.getElementById('facilityRows').appendChild(row);
  row.querySelector('.remove-row').addEventListener('click', function () { row.remove(); });
});
document.querySelectorAll('.remove-row').forEach(function (btn) {
  btn.addEventListener('click', function () { btn.closest('.form-grid').remove(); });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
