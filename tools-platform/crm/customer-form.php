<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$id = (int) ($_GET['id'] ?? 0);
$customer = $id ? tp_query_one('SELECT * FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$id, $bid]) : null;
if ($id && !$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

$error = null;
$old = $customer ?? ['name' => '', 'phone' => '', 'company' => '', 'city' => '', 'business_id_number' => '', 'source' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $old['name'] = tp_sanitize_text($_POST['name'] ?? '', 160);
    $old['phone'] = tp_sanitize_text($_POST['phone'] ?? '', 30);
    $old['company'] = tp_sanitize_text($_POST['company'] ?? '', 160);
    $old['city'] = tp_sanitize_text($_POST['city'] ?? '', 100);
    $old['business_id_number'] = tp_sanitize_text($_POST['business_id_number'] ?? '', 60);
    $old['source'] = tp_sanitize_text($_POST['source'] ?? '', 80);
    $old['notes'] = tp_sanitize_text($_POST['notes'] ?? '', 2000);

    if ($old['name'] === '') {
        $error = 'Customer name is required.';
    } else {
        if ($customer) {
            tp_execute(
                'UPDATE crm_customers SET name=?, phone=?, company=?, city=?, business_id_number=?, source=?, notes=? WHERE id=? AND business_id=?',
                'sssssssii',
                [$old['name'], $old['phone'], $old['company'], $old['city'], $old['business_id_number'], $old['source'], $old['notes'], $id, $bid]
            );
            tp_flash_set('success', 'Customer updated.');
        } else {
            $result = tp_execute(
                'INSERT INTO crm_customers (business_id, name, phone, company, city, business_id_number, source, notes) VALUES (?,?,?,?,?,?,?,?)',
                'isssssss',
                [$bid, $old['name'], $old['phone'], $old['company'], $old['city'], $old['business_id_number'], $old['source'], $old['notes']]
            );
            $id = $result['insert_id'];
            tp_flash_set('success', 'Customer added.');
        }
        header('Location: ' . tp_url('crm/customer-view.php?id=' . $id));
        exit;
    }
}

$crmPageTitle = $customer ? 'Edit Customer' : 'Add Customer';
$crmActive = 'customers';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card" style="max-width:640px;">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= tp_csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required autofocus></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>" placeholder="03001234567"></div>
      <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company" class="form-control" value="<?= e($old['company']) ?>"></div>
      <div class="col-md-6"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e($old['city']) ?>"></div>
      <div class="col-md-6">
        <label class="form-label">CNIC / Business ID <span class="text-muted small">(only if legitimately needed)</span></label>
        <input type="text" name="business_id_number" class="form-control" value="<?= e($old['business_id_number']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Lead Source</label>
        <input type="text" name="source" class="form-control" value="<?= e($old['source']) ?>" placeholder="e.g. Referral, Facebook, Walk-in">
      </div>
      <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"><?= e($old['notes']) ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit"><?= $customer ? 'Save Changes' : 'Add Customer' ?></button>
      <a href="<?= tp_url('crm/customers.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
