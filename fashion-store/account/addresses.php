<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $label = trim($_POST['label'] ?? 'Home');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address_line'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Pakistan');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($isDefault) mysqli_query($mysqli, "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = {$customer['id']}");

        $stmt = mysqli_prepare($mysqli, "INSERT INTO customer_addresses (customer_id, label, full_name, phone, address_line, city, state, postal_code, country, is_default) VALUES (?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'issssssssi', $customer['id'], $label, $fullName, $phone, $address, $city, $state, $postalCode, $country, $isDefault);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Address saved.');
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM customer_addresses WHERE id = " . (int)$_POST['id'] . " AND customer_id = {$customer['id']}");
        flash_set('success', 'Address removed.');
    }
    redirect(url('account/addresses'));
}

$addresses = mysqli_query($mysqli, "SELECT * FROM customer_addresses WHERE customer_id = {$customer['id']} ORDER BY is_default DESC, id DESC");

$pageTitle = 'Saved Addresses | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 font-serif mb-0">Saved Addresses</h1>
        <button class="btn-outline-brand" data-bs-toggle="modal" data-bs-target="#addrModal">Add Address</button>
      </div>
      <div class="row g-3">
      <?php while ($a = mysqli_fetch_assoc($addresses)): ?>
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="d-flex justify-content-between">
              <strong><?= e($a['label']) ?></strong>
              <?php if ($a['is_default']): ?><span class="badge text-bg-success">Default</span><?php endif; ?>
            </div>
            <p class="mb-1 mt-2"><?= e($a['full_name']) ?> &middot; <?= e($a['phone']) ?></p>
            <p class="mb-1 small text-muted"><?= e($a['address_line']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['postal_code']) ?>, <?= e($a['country']) ?></p>
            <form method="post" class="mt-2 confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form>
          </div>
        </div>
      <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addrModal">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="modal-header"><h5 class="modal-title">Add Address</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Label</label><input type="text" name="label" class="form-control mb-2" value="Home">
        <label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control mb-2" value="<?= e($customer['name']) ?>" required>
        <label class="form-label">Phone</label><input type="text" name="phone" class="form-control mb-2" value="<?= e($customer['phone']) ?>" required>
        <label class="form-label">Address</label><input type="text" name="address_line" class="form-control mb-2" required>
        <div class="row g-2 mb-2">
          <div class="col-6"><input type="text" name="city" class="form-control" placeholder="City" required></div>
          <div class="col-6"><input type="text" name="state" class="form-control" placeholder="Province"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><input type="text" name="postal_code" class="form-control" placeholder="Postal Code"></div>
          <div class="col-6"><input type="text" name="country" class="form-control" value="Pakistan"></div>
        </div>
        <div class="form-check"><input type="checkbox" name="is_default" class="form-check-input" id="isDef"><label class="form-check-label" for="isDef">Set as default</label></div>
      </div>
      <div class="modal-footer"><button class="btn-brand">Save Address</button></div>
    </form>
  </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
