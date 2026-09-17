<?php
require __DIR__ . '/../config.php';
wh_require_page_access('customers');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $name = wh_input_post('name');
    $phone = wh_input_post('phone');
    if ($name === '' || $phone === '') {
        wh_flash_set('error', 'Name and phone are required.');
    } else {
        wh_find_or_create_customer($businessId, [
            'name' => $name, 'phone' => $phone, 'whatsapp' => wh_input_post('whatsapp') ?: $phone,
            'email' => wh_input_post('email'), 'address' => wh_input_post('address'),
            'father_husband_name' => wh_input_post('father_husband_name'), 'cnic' => wh_input_post('cnic'),
        ]);
        wh_flash_set('success', 'Customer saved.');
    }
    wh_redirect(BASE_URL . '/admin/customers.php');
}

$search = wh_input_get('q');
$where = 'business_id = ?';
$types = 'i';
$params = [$businessId];
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $types .= 'sss';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$countRow = wh_fetch_one("SELECT COUNT(*) c FROM customers WHERE $where", $types, $params);
$pg = wh_paginate((int) ($countRow['c'] ?? 0), 25);

$customers = wh_fetch_all(
    "SELECT c.*, COUNT(b.id) AS total_bookings, COALESCE(SUM(b.final_total),0) AS total_amount, COALESCE(SUM(b.paid_amount),0) AS total_paid, COALESCE(SUM(b.balance),0) AS total_pending
     FROM customers c LEFT JOIN bookings b ON b.customer_id = c.id AND b.booking_status != 'cancelled'
     WHERE $where GROUP BY c.id ORDER BY c.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $types,
    $params
);

$pageTitle = 'Customers';
$activePage = 'customers';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>All Customers</h3>
    <button type="button" class="btn btn-primary btn-sm" data-modal-open="#customerModal"><i class="fa-solid fa-plus"></i> Add Customer</button>
  </div>
  <form method="get" class="filter-bar">
    <div class="form-group"><label>Search</label><input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, phone, email"></div>
    <button type="submit" class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
  </form>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Bookings</th><th>Total</th><th>Paid</th><th>Pending</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td><?= e($c['phone']) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= (int) $c['total_bookings'] ?></td>
        <td><?= wh_format_money($c['total_amount']) ?></td>
        <td><?= wh_format_money($c['total_paid']) ?></td>
        <td><?= wh_format_money($c['total_pending']) ?></td>
        <td><a href="<?= e(BASE_URL) ?>/admin/customer-view.php?id=<?= (int) $c['id'] ?>" class="btn btn-light btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="8">No customers yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal-overlay" id="customerModal">
  <div class="modal">
    <div class="modal-head"><h3>Add Customer</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= wh_csrf_field() ?>
      <div class="form-group"><label>Name *</label><input type="text" name="name" required></div>
      <div class="form-group"><label>Father/Husband Name</label><input type="text" name="father_husband_name"></div>
      <div class="form-grid">
        <div class="form-group"><label>Phone *</label><input type="text" name="phone" required></div>
        <div class="form-group"><label>WhatsApp</label><input type="text" name="whatsapp"></div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Email</label><input type="email" name="email"></div>
        <div class="form-group"><label>CNIC</label><input type="text" name="cnic"></div>
      </div>
      <div class="form-group"><label>Address</label><input type="text" name="address"></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save Customer</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
