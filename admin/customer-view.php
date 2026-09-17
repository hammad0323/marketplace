<?php
require __DIR__ . '/../config.php';
wh_require_page_access('customer-view');
$businessId = wh_current_business_id();

$id = (int) wh_input_get('id', 0);
$customer = wh_fetch_one('SELECT * FROM customers WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
if (!$customer) {
    wh_flash_set('error', 'Customer not found.');
    wh_redirect(BASE_URL . '/admin/customers.php');
}
$bookings = wh_fetch_all(
    "SELECT b.*, h.name AS hall_name, ts.name AS slot_name FROM bookings b
     JOIN halls h ON h.id=b.hall_id JOIN time_slots ts ON ts.id=b.time_slot_id
     WHERE b.customer_id=? ORDER BY b.booking_date DESC",
    'i',
    [$id]
);
$totals = wh_fetch_one(
    "SELECT COUNT(*) AS n, COALESCE(SUM(final_total),0) AS total, COALESCE(SUM(paid_amount),0) AS paid, COALESCE(SUM(balance),0) AS pending
     FROM bookings WHERE customer_id=? AND booking_status != 'cancelled'",
    'i',
    [$id]
);

$pageTitle = $customer['name'];
$activePage = 'customers';
require __DIR__ . '/header.php';
?>
<div class="two-col">
  <div>
    <div class="admin-card">
      <h3 style="margin-bottom:16px;">Booking History</h3>
      <div class="table-scroll">
      <table class="admin-table">
        <thead><tr><th>Booking</th><th>Hall</th><th>Date</th><th>Slot</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
          <tr>
            <td><?= e($b['booking_code']) ?></td>
            <td><?= e($b['hall_name']) ?></td>
            <td><?= wh_format_date($b['booking_date']) ?></td>
            <td><?= e($b['slot_name']) ?></td>
            <td><?= wh_format_money($b['final_total']) ?></td>
            <td><?= wh_format_money($b['balance']) ?></td>
            <td><span class="badge badge-<?= e($b['booking_status']) ?>"><?= e(ucfirst($b['booking_status'])) ?></span></td>
            <td><a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $b['id'] ?>" class="btn btn-light btn-sm">View</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$bookings): ?><tr><td colspan="8">No bookings yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <div>
    <div class="admin-card">
      <h3 style="margin-bottom:16px;"><?= e($customer['name']) ?></h3>
      <table class="simple">
        <?php if ($customer['father_husband_name']): ?><tr><th>Father/Husband</th><td><?= e($customer['father_husband_name']) ?></td></tr><?php endif; ?>
        <tr><th>Phone</th><td><?= e($customer['phone']) ?></td></tr>
        <?php if ($customer['whatsapp']): ?><tr><th>WhatsApp</th><td><?= e($customer['whatsapp']) ?></td></tr><?php endif; ?>
        <?php if ($customer['email']): ?><tr><th>Email</th><td><?= e($customer['email']) ?></td></tr><?php endif; ?>
        <?php if ($customer['cnic']): ?><tr><th>CNIC</th><td><?= e($customer['cnic']) ?></td></tr><?php endif; ?>
        <?php if ($customer['address']): ?><tr><th>Address</th><td><?= e($customer['address']) ?></td></tr><?php endif; ?>
      </table>
    </div>
    <div class="admin-card">
      <h4>Totals</h4>
      <table class="simple">
        <tr><th>Bookings</th><td><?= (int) $totals['n'] ?></td></tr>
        <tr><th>Total Amount</th><td><?= wh_format_money($totals['total']) ?></td></tr>
        <tr><th>Total Paid</th><td><?= wh_format_money($totals['paid']) ?></td></tr>
        <tr><th>Total Pending</th><td><?= wh_format_money($totals['pending']) ?></td></tr>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
