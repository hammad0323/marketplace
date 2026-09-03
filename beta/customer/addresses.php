<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'];
    if ($action === 'add') {
        if (!empty($_POST['is_default'])) db_exec("UPDATE addresses SET is_default=0 WHERE customer_id=?", 'i', [$customer['id']]);
        db_insert("INSERT INTO addresses (customer_id,label,full_name,phone,address_line,city,state,country,postal_code,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)",
            'issssssssi', [$customer['id'], trim($_POST['label']), trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['address_line']),
                trim($_POST['city']), trim($_POST['state']), trim($_POST['country']), trim($_POST['postal_code']), isset($_POST['is_default']) ? 1 : 0]);
        flash('success', 'Address added.');
    } elseif ($action === 'delete') {
        db_exec("DELETE FROM addresses WHERE id=? AND customer_id=?", 'ii', [(int)$_POST['id'], $customer['id']]);
        flash('success', 'Address removed.');
    } elseif ($action === 'set_default') {
        db_exec("UPDATE addresses SET is_default=0 WHERE customer_id=?", 'i', [$customer['id']]);
        db_exec("UPDATE addresses SET is_default=1 WHERE id=? AND customer_id=?", 'ii', [(int)$_POST['id'], $customer['id']]);
        flash('success', 'Default address updated.');
    }
    redirect(customer_url('addresses.php'));
}

$addresses = db_fetch_all("SELECT * FROM addresses WHERE customer_id=? ORDER BY is_default DESC", 'i', [$customer['id']]);
$dashRole = 'customer'; $pageTitle = 'My Addresses'; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>My Addresses</h1></div>
<div class="dash-two-col">
  <div class="dash-table-card">
    <?php foreach ($addresses as $a): ?>
      <div class="address-card">
        <strong><?= clean($a['label']) ?></strong> <?php if ($a['is_default']): ?><span class="badge badge-success">Default</span><?php endif; ?>
        <p><?= clean($a['full_name']) ?><br><?= clean($a['address_line']) ?>, <?= clean($a['city']) ?>, <?= clean($a['state']) ?>, <?= clean($a['country']) ?> <?= clean($a['postal_code']) ?><br><?= clean($a['phone']) ?></p>
        <form method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?= $a['id'] ?>">
          <?php if (!$a['is_default']): ?><button class="btn btn-sm btn-outline">Set Default</button><?php endif; ?>
        </form>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this address?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$addresses): ?><div class="empty-state"><h3>No saved addresses</h3></div><?php endif; ?>
  </div>
  <div class="dash-form-card">
    <h3>Add New Address</h3>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Label</label><input type="text" name="label" value="Home" required>
      <label>Full Name</label><input type="text" name="full_name" required>
      <label>Phone</label><input type="text" name="phone" required>
      <label>Address</label><input type="text" name="address_line" required>
      <div class="form-row">
        <div><label>City</label><input type="text" name="city" required></div>
        <div><label>State</label><input type="text" name="state"></div>
      </div>
      <div class="form-row">
        <div><label>Country</label><input type="text" name="country" value="Pakistan"></div>
        <div><label>Postal Code</label><input type="text" name="postal_code"></div>
      </div>
      <label class="switch-label"><input type="checkbox" name="is_default"> Set as default</label>
      <button type="submit" class="btn btn-primary">Save Address</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
