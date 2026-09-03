<?php
require __DIR__ . '/../config/config.php';
require_permission('manage_shop_profile');
$shopId = active_shop_id();
$shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$shopId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $shopName = trim($_POST['shop_name']); $description = clean_html($_POST['description'] ?? '');
    $address = trim($_POST['business_address']); $city = trim($_POST['city']); $country = trim($_POST['country']);
    $phone = trim($_POST['phone']); $email = trim($_POST['email']);

    $logoUpload = handle_image_upload('logo', 'shops');
    $coverUpload = handle_image_upload('cover_image', 'shops');
    if (isset($logoUpload['error'])) { flash('error', $logoUpload['error']); redirect(shop_url('profile.php')); }
    if (isset($coverUpload['error'])) { flash('error', $coverUpload['error']); redirect(shop_url('profile.php')); }

    db_exec("UPDATE shops SET shop_name=?,description=?,business_address=?,city=?,country=?,phone=?,email=?,logo=?,cover_image=? WHERE id=?",
        'sssssssssi', [$shopName, $description, $address, $city, $country, $phone, $email,
            $logoUpload['path'] ?? $shop['logo'], $coverUpload['path'] ?? $shop['cover_image'], $shopId]);
    flash('success', 'Shop profile updated.');
    redirect(shop_url('profile.php'));
}

$dashRole = current_shop_owner() ? 'shop' : 'employee'; $pageTitle = 'Shop Profile';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shop Profile</h1></div>
<form method="post" enctype="multipart/form-data" class="dash-form-card">
  <?= csrf_field() ?>
  <label>Shop Name</label><input type="text" name="shop_name" value="<?= clean($shop['shop_name']) ?>" required>
  <label>Description</label><textarea name="description" rows="4"><?= clean($shop['description']) ?></textarea>
  <div class="form-row">
    <div><label>Business Address</label><input type="text" name="business_address" value="<?= clean($shop['business_address']) ?>"></div>
    <div><label>City</label><input type="text" name="city" value="<?= clean($shop['city']) ?>"></div>
    <div><label>Country</label><input type="text" name="country" value="<?= clean($shop['country']) ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Phone</label><input type="text" name="phone" value="<?= clean($shop['phone']) ?>"></div>
    <div><label>Email</label><input type="email" name="email" value="<?= clean($shop['email']) ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Logo</label><img class="table-thumb" src="<?= shop_logo_or_default($shop['logo']) ?>"><input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"></div>
    <div><label>Cover Image</label><img class="table-thumb-wide" src="<?= shop_cover_or_default($shop['cover_image']) ?>"><input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp"></div>
  </div>
  <button type="submit" class="btn btn-primary">Save Profile</button>
</form>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
