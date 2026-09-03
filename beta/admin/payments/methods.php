<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($_POST['methods'] as $id => $data) {
        $enabled = isset($data['enabled']) ? 1 : 0;
        db_exec("UPDATE payment_methods SET is_enabled=?, title=?, description=?, instructions=? WHERE id=?",
            'isssi', [$enabled, trim($data['title']), trim($data['description']), trim($data['instructions']), (int)$id]);
    }
    flash('success', 'Payment methods updated.');
    redirect(admin_url('payments/methods.php'));
}

$methods = db_fetch_all("SELECT * FROM payment_methods ORDER BY sort_order");
$dashRole = 'admin'; $pageTitle = 'Payment Methods'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Payment Methods</h1></div>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($methods as $m): ?>
    <div class="dash-form-card">
      <div class="form-row">
        <label class="switch-label"><input type="checkbox" name="methods[<?= $m['id'] ?>][enabled]" <?= $m['is_enabled'] ? 'checked' : '' ?>> Enabled</label>
        <div><label>Title</label><input type="text" name="methods[<?= $m['id'] ?>][title]" value="<?= clean($m['title']) ?>"></div>
      </div>
      <label>Description</label><input type="text" name="methods[<?= $m['id'] ?>][description]" value="<?= clean($m['description']) ?>">
      <label>Instructions (shown to customer/vendor)</label>
      <textarea name="methods[<?= $m['id'] ?>][instructions]" rows="2"><?= clean($m['instructions']) ?></textarea>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-primary">Save All</button>
</form>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
