<?php
require __DIR__ . '/../config/config.php';
require_permission('manage_shop_design');
$shopId = active_shop_id();
$shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$shopId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    db_exec("UPDATE shops SET primary_color=?, secondary_color=? WHERE id=?", 'ssi', [$_POST['primary_color'], $_POST['secondary_color'], $shopId]);
    flash('success', 'Shop design updated.');
    redirect(shop_url('shop-design.php'));
}

$dashRole = 'shop'; $pageTitle = 'Shop Design';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shop Design</h1>
  <a href="<?= base_url('shop.php?slug=' . $shop['slug']) ?>" target="_blank" class="btn btn-outline">Preview Shop Page</a>
</div>
<form method="post" class="dash-form-card">
  <?= csrf_field() ?>
  <h3>Brand Colors</h3>
  <p class="text-muted">These colors are used as accents on your public shop page.</p>
  <div class="form-row">
    <div><label>Primary Color</label><input type="color" name="primary_color" value="<?= clean($shop['primary_color']) ?>"></div>
    <div><label>Secondary Color</label><input type="color" name="secondary_color" value="<?= clean($shop['secondary_color']) ?>"></div>
  </div>
  <button type="submit" class="btn btn-primary">Save Design</button>
</form>
<div class="dash-form-card">
  <h3>Cover &amp; Logo</h3>
  <p class="text-muted">Update your shop's cover image and logo from <a href="<?= shop_url('profile.php') ?>">Shop Profile</a>.</p>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
