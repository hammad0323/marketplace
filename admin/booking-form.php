<?php
require __DIR__ . '/../config.php';
wh_require_page_access('booking-form');
$businessId = wh_current_business_id();

$id = (int) wh_input_get('id', 0);
$booking = $id ? wh_get_booking($id, $businessId) : null;
if ($id && !$booking) {
    wh_flash_set('error', 'Booking not found.');
    wh_redirect(BASE_URL . '/admin/bookings.php');
}

$halls = wh_get_halls($businessId);
$slots = wh_get_time_slots($businessId);
$eventTypes = wh_get_event_types($businessId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();

    $customerName = wh_input_post('customer_name');
    $customerPhone = wh_input_post('customer_phone');
    $guests = (int) wh_input_post('guests');
    $packageName = wh_input_post('package_name');
    $perPersonPrice = wh_decimal(wh_input_post('per_person_price', 0));
    $totalAmount = wh_decimal(wh_input_post('total_amount', 0));
    $additionalCharges = wh_decimal(wh_input_post('additional_charges', 0));
    $discount = wh_decimal(wh_input_post('discount', 0));
    $finalTotal = max(0, $totalAmount + $additionalCharges - $discount);
    $advanceRequired = wh_decimal(wh_input_post('advance_required', 0));
    $eventTypeId = (int) wh_input_post('event_type_id') ?: null;
    $notes = wh_input_post('notes');
    $nextPaymentDate = wh_input_post('next_payment_date') ?: null;

    if ($customerName === '' || $customerPhone === '') $errors[] = 'Customer name and phone are required.';
    if ($guests < 1) $errors[] = 'Please enter number of guests.';

    if ($id) {
        // Editing: customer/package/pricing/notes only — hall/date/slot are locked once created.
        if (!$errors) {
            $customerId = wh_find_or_create_customer($businessId, [
                'name' => $customerName, 'phone' => $customerPhone,
                'whatsapp' => wh_input_post('customer_whatsapp') ?: $customerPhone,
                'email' => wh_input_post('customer_email'), 'address' => wh_input_post('customer_address'),
                'father_husband_name' => wh_input_post('father_husband_name'), 'cnic' => wh_input_post('customer_cnic'),
            ]);
            wh_update('bookings', [
                'customer_id' => $customerId, 'event_type_id' => $eventTypeId, 'guests' => $guests,
                'package_name' => $packageName, 'per_person_price' => $perPersonPrice, 'total_amount' => $totalAmount,
                'additional_charges' => $additionalCharges, 'discount' => $discount, 'final_total' => $finalTotal,
                'advance_required' => $advanceRequired, 'next_payment_date' => $nextPaymentDate, 'notes' => $notes,
            ], 'id = ? AND business_id = ?', [$id, $businessId]);
            wh_recalc_booking_payment($id, $businessId);
            wh_flash_set('success', 'Booking updated.');
            wh_redirect(BASE_URL . '/admin/booking-view.php?id=' . $id);
        }
    } else {
        $hallId = (int) wh_input_post('hall_id');
        $timeSlotId = (int) wh_input_post('time_slot_id');
        $bookingDate = wh_input_post('booking_date');
        $bookingStatus = wh_input_post('booking_status') ?: 'confirmed';
        if (!$hallId || !$timeSlotId || !$bookingDate) $errors[] = 'Hall, date and time slot are required.';

        if (!$errors) {
            $customerId = wh_find_or_create_customer($businessId, [
                'name' => $customerName, 'phone' => $customerPhone,
                'whatsapp' => wh_input_post('customer_whatsapp') ?: $customerPhone,
                'email' => wh_input_post('customer_email'), 'address' => wh_input_post('customer_address'),
                'father_husband_name' => wh_input_post('father_husband_name'), 'cnic' => wh_input_post('customer_cnic'),
            ]);
            $result = wh_create_booking([
                'business_id' => $businessId, 'hall_id' => $hallId, 'customer_id' => $customerId,
                'event_type_id' => $eventTypeId, 'time_slot_id' => $timeSlotId, 'booking_date' => $bookingDate,
                'guests' => $guests, 'package_name' => $packageName, 'per_person_price' => $perPersonPrice,
                'total_amount' => $totalAmount, 'additional_charges' => $additionalCharges, 'discount' => $discount,
                'final_total' => $finalTotal, 'advance_required' => $advanceRequired, 'booking_status' => $bookingStatus,
                'source' => 'admin', 'next_payment_date' => $nextPaymentDate, 'notes' => $notes,
                'created_by' => $_SESSION['admin_id'],
            ]);
            if ($result['ok']) {
                wh_flash_set('success', 'Booking created: ' . $result['booking_code']);
                wh_redirect(BASE_URL . '/admin/booking-view.php?id=' . $result['booking_id']);
            } elseif ($result['error'] === 'conflict') {
                $errors[] = 'This hall is already booked for the selected date and time slot.';
            } else {
                $errors[] = 'Could not create booking. Please check the form.';
            }
        }
    }
}

$pageTitle = $id ? 'Edit Booking' : 'Add Booking';
$activePage = 'booking-form';
require __DIR__ . '/header.php';
?>
<form method="post">
  <?= wh_csrf_field() ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Event Details</h3>
    <?php if ($id): ?>
      <div class="alert alert-info">Hall: <strong><?= e($booking['hall_name']) ?></strong> · Date: <strong><?= wh_format_date($booking['booking_date']) ?></strong> · Slot: <strong><?= e($booking['slot_name']) ?></strong><br>To change the hall, date or time slot, cancel this booking and create a new one.</div>
    <?php else: ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Hall *</label>
        <select name="hall_id" id="hallSelect" required>
          <option value="">Select hall</option>
          <?php foreach ($halls as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Date *</label><input type="date" name="booking_date" id="bookingDate" required></div>
      <div class="form-group"><label>Time Slot *</label>
        <select name="time_slot_id" id="slotSelect" required>
          <option value="">Select slot</option>
          <?php foreach ($slots as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> (<?= wh_format_time($s['start_time']) ?>–<?= wh_format_time($s['end_time']) ?>)</option><?php endforeach; ?>
        </select>
        <p class="hint" id="slotHint"></p>
      </div>
    </div>
    <div class="form-group"><label>Booking Status</label>
      <select name="booking_status"><option value="confirmed">Confirmed</option><option value="pending">Pending</option><option value="hold">Hold</option></select>
    </div>
    <?php endif; ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Event Type</label>
        <select name="event_type_id"><option value="">—</option>
          <?php foreach ($eventTypes as $et): ?><option value="<?= (int) $et['id'] ?>" <?= (int) ($booking['event_type_id'] ?? 0) === (int) $et['id'] ? 'selected' : '' ?>><?= e($et['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Number of Guests *</label><input type="number" name="guests" min="1" value="<?= e($booking['guests'] ?? '') ?>" required></div>
      <div class="form-group"><label>Package Name</label><input type="text" name="package_name" value="<?= e($booking['package_name'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Customer Information</h3>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Customer Name *</label><input type="text" name="customer_name" value="<?= e($booking['customer_name'] ?? '') ?>" required></div>
      <div class="form-group"><label>Father/Husband Name</label><input type="text" name="father_husband_name" value="<?= e($booking['father_husband_name'] ?? '') ?>"></div>
      <div class="form-group"><label>CNIC</label><input type="text" name="customer_cnic" value="<?= e($booking['customer_cnic'] ?? '') ?>"></div>
    </div>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Phone *</label><input type="text" name="customer_phone" value="<?= e($booking['customer_phone'] ?? '') ?>" required></div>
      <div class="form-group"><label>WhatsApp</label><input type="text" name="customer_whatsapp" value="<?= e($booking['customer_whatsapp'] ?? '') ?>"></div>
      <div class="form-group"><label>Email</label><input type="email" name="customer_email" value="<?= e($booking['customer_email'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Address</label><input type="text" name="customer_address" value="<?= e($booking['customer_address'] ?? '') ?>"></div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Pricing</h3>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Per Person Price</label><input type="number" step="0.01" name="per_person_price" id="perPersonPrice" value="<?= e($booking['per_person_price'] ?? 0) ?>"></div>
      <div class="form-group"><label>Total Amount (Package)</label><input type="number" step="0.01" name="total_amount" id="totalAmount" value="<?= e($booking['total_amount'] ?? 0) ?>"></div>
      <div class="form-group"><label>Additional Charges</label><input type="number" step="0.01" name="additional_charges" id="additionalCharges" value="<?= e($booking['additional_charges'] ?? 0) ?>"></div>
    </div>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Discount</label><input type="number" step="0.01" name="discount" id="discount" value="<?= e($booking['discount'] ?? 0) ?>"></div>
      <div class="form-group"><label>Final Total</label><input type="text" id="finalTotalDisplay" value="<?= wh_format_money($booking['final_total'] ?? 0) ?>" disabled></div>
      <div class="form-group"><label>Advance Required</label><input type="number" step="0.01" name="advance_required" value="<?= e($booking['advance_required'] ?? 0) ?>"></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Next Payment Due Date</label><input type="date" name="next_payment_date" value="<?= e($booking['next_payment_date'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= e($booking['notes'] ?? '') ?></textarea></div>
  </div>

  <button type="submit" class="btn btn-primary">Save Booking</button>
  <a href="<?= e(BASE_URL) ?>/admin/bookings.php" class="btn btn-light">Cancel</a>
</form>

<?php if (!$id): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
function checkAdminSlot() {
  var hall = document.getElementById('hallSelect').value;
  var date = document.getElementById('bookingDate').value;
  var hint = document.getElementById('slotHint');
  if (!hall || !date) return;
  WH.fetchAvailability(date, hall).then(function (data) {
    if (!data.success || !data.halls || !data.halls.length) return;
    var statuses = {};
    data.halls[0].slots.forEach(function (s) { statuses[s.id] = s.status; });
    var options = document.querySelectorAll('#slotSelect option');
    options.forEach(function (opt) {
      if (!opt.value) return;
      opt.textContent = opt.textContent.replace(' (Booked)', '');
      if (statuses[opt.value] === 'booked') { opt.textContent += ' (Booked)'; }
    });
    hint.textContent = 'Slots already booked for this date are marked "(Booked)".';
  });
}
document.getElementById('hallSelect').addEventListener('change', checkAdminSlot);
document.getElementById('bookingDate').addEventListener('change', checkAdminSlot);
</script>
<?php endif; ?>
<script>
function recalcFinalTotal() {
  var total = parseFloat(document.getElementById('totalAmount').value) || 0;
  var add = parseFloat(document.getElementById('additionalCharges').value) || 0;
  var disc = parseFloat(document.getElementById('discount').value) || 0;
  var final = Math.max(0, total + add - disc);
  document.getElementById('finalTotalDisplay').value = 'PKR ' + final.toLocaleString();
}
['totalAmount', 'additionalCharges', 'discount'].forEach(function (id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('input', recalcFinalTotal);
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
