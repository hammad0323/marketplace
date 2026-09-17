<?php
require __DIR__ . '/../config.php';
wh_require_page_access('booking-view');
$businessId = wh_current_business_id();

$id = (int) wh_input_get('id', 0);
$booking = wh_get_booking($id, $businessId);
if (!$booking) {
    wh_flash_set('error', 'Booking not found.');
    wh_redirect(BASE_URL . '/admin/bookings.php');
}
$payments = wh_get_payments($id);
$settings = wh_get_settings($businessId);

$statusFlow = [
    'pending' => ['confirmed' => 'Confirm', 'hold' => 'Put on Hold', 'cancelled' => 'Reject/Cancel'],
    'hold' => ['confirmed' => 'Confirm', 'cancelled' => 'Cancel'],
    'confirmed' => ['completed' => 'Mark Completed', 'cancelled' => 'Cancel'],
    'completed' => [],
    'cancelled' => ['pending' => 'Reactivate (Pending)'],
];

$pageTitle = 'Booking ' . $booking['booking_code'];
$activePage = 'bookings';
require __DIR__ . '/header.php';
?>
<div id="printArea">
<div class="admin-card">
  <div class="card-head">
    <div>
      <h3 style="margin-bottom:4px;"><?= e($booking['booking_code']) ?></h3>
      <span class="badge badge-<?= e($booking['booking_status']) ?>"><?= e(ucfirst($booking['booking_status'])) ?></span>
      <span class="badge badge-<?= e($booking['payment_status']) ?>"><?= e(ucfirst($booking['payment_status'])) ?></span>
      <span class="badge badge-<?= $booking['source'] === 'online' ? 'confirmed' : 'active' ?>"><?= e(ucfirst($booking['source'])) ?></span>
    </div>
    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach ($statusFlow[$booking['booking_status']] ?? [] as $newStatus => $label): ?>
        <button type="button" class="btn btn-light btn-sm status-btn" data-status="<?= e($newStatus) ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
      <a href="<?= e(BASE_URL) ?>/admin/booking-form.php?id=<?= (int) $booking['id'] ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
      <button type="button" class="btn btn-light btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
  </div>
  <div id="statusMsg"></div>

  <div class="two-col">
    <div>
      <h4>Event Details</h4>
      <table class="simple">
        <tr><th>Hall</th><td><?= e($booking['hall_name']) ?></td></tr>
        <tr><th>Date</th><td><?= wh_format_date($booking['booking_date']) ?></td></tr>
        <tr><th>Time Slot</th><td><?= e($booking['slot_name']) ?> (<?= wh_format_time($booking['start_time']) ?>–<?= wh_format_time($booking['end_time']) ?>)</td></tr>
        <tr><th>Event Type</th><td><?= e($booking['event_type_name'] ?? '—') ?></td></tr>
        <tr><th>Guests</th><td><?= (int) $booking['guests'] ?></td></tr>
        <tr><th>Package</th><td><?= e($booking['package_name'] ?? '—') ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br(e($booking['notes'] ?? '—')) ?></td></tr>
        <tr><th>Created</th><td><?= wh_format_date($booking['created_at'], 'd M Y, g:i A') ?></td></tr>
      </table>

      <h4 style="margin-top:22px;">Customer</h4>
      <table class="simple">
        <tr><th>Name</th><td><?= e($booking['customer_name']) ?></td></tr>
        <?php if ($booking['father_husband_name']): ?><tr><th>Father/Husband</th><td><?= e($booking['father_husband_name']) ?></td></tr><?php endif; ?>
        <tr><th>Phone</th><td><?= e($booking['customer_phone']) ?></td></tr>
        <?php if ($booking['customer_whatsapp']): ?><tr><th>WhatsApp</th><td><?= e($booking['customer_whatsapp']) ?></td></tr><?php endif; ?>
        <?php if ($booking['customer_email']): ?><tr><th>Email</th><td><?= e($booking['customer_email']) ?></td></tr><?php endif; ?>
        <?php if ($booking['customer_cnic']): ?><tr><th>CNIC</th><td><?= e($booking['customer_cnic']) ?></td></tr><?php endif; ?>
        <?php if ($booking['customer_address']): ?><tr><th>Address</th><td><?= e($booking['customer_address']) ?></td></tr><?php endif; ?>
      </table>
    </div>

    <div>
      <h4>Payment Summary</h4>
      <table class="simple">
        <tr><th>Total Amount</th><td><?= wh_format_money($booking['total_amount']) ?></td></tr>
        <tr><th>Additional Charges</th><td><?= wh_format_money($booking['additional_charges']) ?></td></tr>
        <tr><th>Discount</th><td>- <?= wh_format_money($booking['discount']) ?></td></tr>
        <tr><th><strong>Final Total</strong></th><td><strong><?= wh_format_money($booking['final_total']) ?></strong></td></tr>
        <tr><th>Advance Required</th><td><?= wh_format_money($booking['advance_required']) ?></td></tr>
        <tr><th>Paid</th><td style="color:var(--a-success);font-weight:600;"><?= wh_format_money($booking['paid_amount']) ?></td></tr>
        <tr><th>Balance</th><td style="color:var(--a-danger);font-weight:600;"><?= wh_format_money($booking['balance']) ?></td></tr>
        <?php if ($booking['next_payment_date']): ?><tr><th>Next Payment Due</th><td><?= wh_format_date($booking['next_payment_date']) ?></td></tr><?php endif; ?>
      </table>

      <div class="card-head no-print" style="margin-top:22px;">
        <h4 style="margin:0;">Payment History</h4>
        <button type="button" class="btn btn-primary btn-sm" data-modal-open="#paymentModal"><i class="fa-solid fa-plus"></i> Add Payment</button>
      </div>
      <table class="simple">
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead>
        <tbody id="paymentRows">
          <?php foreach ($payments as $p): ?>
          <tr><td><?= wh_format_date($p['payment_date']) ?></td><td><?= wh_format_money($p['amount']) ?></td><td><?= e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td><td><?= e($p['notes']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$payments): ?><tr><td colspan="4">No payments recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<div class="modal-overlay" id="paymentModal">
  <div class="modal">
    <div class="modal-head"><h3>Add Payment</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form id="paymentForm">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
      <div class="form-group"><label>Amount (PKR)</label><input type="number" step="0.01" name="amount" required></div>
      <div class="form-grid">
        <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="form-group"><label>Method</label>
          <select name="payment_method">
            <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option>
            <option value="jazzcash">JazzCash</option><option value="easypaisa">Easypaisa</option>
            <option value="card">Card</option><option value="other">Other</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Notes</label><input type="text" name="notes"></div>
      <div id="paymentFormMsg"></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save Payment</button>
    </form>
  </div>
</div>

<style>
@media print {
  .admin-sidebar, .admin-topbar, .no-print, .modal-overlay { display: none !important; }
  .admin-main, .admin-content { margin: 0 !important; padding: 0 !important; }
}
</style>
<script>
document.querySelectorAll('.status-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (!confirm('Change booking status to "' + btn.textContent.trim() + '"?')) return;
    var fd = new FormData();
    fd.append('csrf_token', '<?= e(wh_csrf_token()) ?>');
    fd.append('booking_id', '<?= (int) $booking['id'] ?>');
    fd.append('status', btn.getAttribute('data-status'));
    fetch('/ajax/booking-status.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.success) { location.reload(); }
      else { document.getElementById('statusMsg').innerHTML = '<div class="alert alert-error">' + data.message + '</div>'; }
    });
  });
});
document.getElementById('paymentForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var fd = new FormData(this);
  fetch('/ajax/add-payment.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (data) {
    if (data.success) { location.reload(); }
    else { document.getElementById('paymentFormMsg').innerHTML = '<div class="alert alert-error">' + data.message + '</div>'; }
  });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
