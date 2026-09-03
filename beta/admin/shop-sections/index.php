<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($_POST['sections'] as $id => $data) {
        $status = isset($data['status']) ? 'active' : 'inactive';
        db_exec("UPDATE homepage_sections SET heading=?, description=?, item_count=?, sort_order=?, status=? WHERE id=?",
            'ssiisi', [trim($data['heading']), trim($data['description']), (int)$data['item_count'], (int)$data['sort_order'], $status, (int)$id]);
    }
    flash('success', 'Homepage sections updated.');
    redirect(admin_url('shop-sections/index.php'));
}

$sections = db_fetch_all("SELECT * FROM homepage_sections ORDER BY sort_order");
$dashRole = 'admin'; $pageTitle = 'Homepage Sections'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Homepage Sections</h1></div>
<p class="text-muted">Enable, disable, reorder and configure the sections shown on the homepage.</p>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($sections as $s): ?>
    <div class="dash-form-card">
      <div class="form-row">
        <strong><?= clean(ucwords(str_replace('_', ' ', $s['section_type']))) ?></strong>
        <label class="switch-label"><input type="checkbox" name="sections[<?= $s['id'] ?>][status]" <?= $s['status'] === 'active' ? 'checked' : '' ?>> Active</label>
      </div>
      <div class="form-row">
        <div><label>Heading</label><input type="text" name="sections[<?= $s['id'] ?>][heading]" value="<?= clean($s['heading']) ?>"></div>
        <div><label>Description</label><input type="text" name="sections[<?= $s['id'] ?>][description]" value="<?= clean($s['description']) ?>"></div>
      </div>
      <div class="form-row">
        <div><label>Number of Items</label><input type="number" name="sections[<?= $s['id'] ?>][item_count]" value="<?= $s['item_count'] ?>"></div>
        <div><label>Sort Order</label><input type="number" name="sections[<?= $s['id'] ?>][sort_order]" value="<?= $s['sort_order'] ?>"></div>
      </div>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-primary">Save All Sections</button>
</form>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
