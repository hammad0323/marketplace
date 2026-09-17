<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
$halls = wh_get_halls($businessId, true);
$eventTypes = wh_get_event_types($businessId);
$timeSlots = wh_get_time_slots($businessId);
$allowOnline = wh_setting_bool('allow_online_booking', true, $businessId);
$allowHallSelect = wh_setting_bool('allow_hall_select', true, $businessId);
$allowSlotSelect = wh_setting_bool('allow_timeslot_select', true, $businessId);
$requireConfirmation = wh_setting_bool('require_admin_confirmation', true, $businessId);
$minAdvancePct = (float) ($settings['minimum_advance_percent'] ?? 20);

$errors = [];
$old = $_POST;
$preselectHall = (int) wh_input_get('hall', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowOnline) {
    wh_csrf_verify();

    $name = wh_input_post('name');
    $phone = wh_input_post('phone');
    $whatsapp = wh_input_post('whatsapp') ?: $phone;
    $email = wh_input_post('email');
    $address = wh_input_post('address');
    $hallId = (int) wh_input_post('hall_id');
    $timeSlotId = (int) wh_input_post('time_slot_id');
    $eventTypeId = (int) wh_input_post('event_type_id');
    $bookingDate = wh_input_post('booking_date');
    $guests = (int) wh_input_post('guests');
    $message = wh_input_post('message');

    if ($name === '') $errors[] = 'Please enter your name.';
    if ($phone === '') $errors[] = 'Please enter a phone number.';
    if (!$hallId) $errors[] = 'Please select a hall.';
    if (!$timeSlotId) $errors[] = 'Please select a time slot.';
    if (!$bookingDate || strtotime($bookingDate) < strtotime(date('Y-m-d'))) $errors[] = 'Please select a valid, upcoming date.';
    if ($guests < 1) $errors[] = 'Please enter the expected number of guests.';

    $hall = $hallId ? wh_get_hall($hallId, $businessId) : null;
    if ($hallId && !$hall) $errors[] = 'Selected hall is invalid.';

    if (!$errors) {
        $total = $hall['price_type'] === 'per_person' ? $guests * (float) $hall['per_person_price'] : (float) $hall['base_price'];
        $advance = wh_setting_bool('enable_advance_payment', true, $businessId) ? round($total * $minAdvancePct / 100) : 0;

        $customerId = wh_find_or_create_customer($businessId, [
            'name' => $name, 'phone' => $phone, 'whatsapp' => $whatsapp, 'email' => $email, 'address' => $address,
        ]);

        $result = wh_create_booking([
            'business_id' => $businessId, 'hall_id' => $hallId, 'customer_id' => $customerId,
            'event_type_id' => $eventTypeId ?: null, 'time_slot_id' => $timeSlotId, 'booking_date' => $bookingDate,
            'guests' => $guests, 'package_name' => $hall['name'] . ' — Standard Package',
            'per_person_price' => $hall['per_person_price'], 'total_amount' => $total, 'final_total' => $total,
            'advance_required' => $advance, 'booking_status' => $requireConfirmation ? 'pending' : 'confirmed',
            'source' => 'online', 'notes' => $message,
        ]);

        if ($result['ok']) {
            wh_add_notification($businessId, 'new_booking', 'New online booking request: ' . $result['booking_code'],
                $name . ' — ' . $hall['name'] . ' — ' . wh_format_date($bookingDate), '/admin/booking-view.php?id=' . $result['booking_id']);
            $_SESSION['recent_booking_codes'][] = $result['booking_code'];
            wh_redirect(BASE_URL . '/booking-confirmation/' . $result['booking_code']);
        } elseif ($result['error'] === 'conflict') {
            $errors[] = 'Sorry, this date and time slot is already booked. Please select another available slot.';
        } else {
            $errors[] = 'We could not process your booking. Please check the form and try again.';
        }
    }
}

$pageTitle = 'Book Online';
$seoPageKey = 'booking';
$activeNav = 'booking';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Book Your Hall Online</h1>
    <p>Select a hall, date and time slot — we'll confirm availability instantly.</p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:820px;">
    <?php if (!$allowOnline): ?>
      <div class="alert alert-info">Online booking is currently unavailable. Please <a href="<?= e(BASE_URL) ?>/contact">contact us</a> directly to book a hall.</div>
    <?php else: ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form-card reveal" id="bookingForm">
      <?= wh_csrf_field() ?>
      <h3 style="margin-bottom:18px;">Event Details</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Hall <span class="req">*</span></label>
          <?php if ($allowHallSelect): ?>
          <select name="hall_id" id="hallSelect" required>
            <option value="">Select hall</option>
            <?php foreach ($halls as $hall): ?><option value="<?= (int) $hall['id'] ?>" <?= ($old['hall_id'] ?? $preselectHall) == $hall['id'] ? 'selected' : '' ?>><?= e($hall['name']) ?> — <?= e($hall['city']) ?></option><?php endforeach; ?>
          </select>
          <?php else: $hall = $halls[0] ?? null; ?>
            <input type="text" value="<?= e($hall['name'] ?? '') ?>" disabled>
            <input type="hidden" name="hall_id" id="hallSelect" value="<?= (int) ($hall['id'] ?? 0) ?>">
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Event Type <span class="req">*</span></label>
          <select name="event_type_id" required>
            <option value="">Select event type</option>
            <?php foreach ($eventTypes as $et): ?><option value="<?= (int) $et['id'] ?>" <?= ($old['event_type_id'] ?? '') == $et['id'] ? 'selected' : '' ?>><?= e($et['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Event Date <span class="req">*</span></label>
          <input type="date" name="booking_date" id="bookingDate" min="<?= e(date('Y-m-d')) ?>" value="<?= e($old['booking_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Number of Guests <span class="req">*</span></label>
          <input type="number" name="guests" min="1" value="<?= e($old['guests'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Time Slot <span class="req">*</span></label>
        <div id="slotOptions" class="badge-row">
          <?php foreach ($timeSlots as $slot): ?>
          <label class="chip" style="cursor:pointer;">
            <input type="radio" name="time_slot_id" value="<?= (int) $slot['id'] ?>" <?= ($old['time_slot_id'] ?? '') == $slot['id'] ? 'checked' : '' ?> style="width:auto;margin-right:6px;" <?= $allowSlotSelect ? '' : 'disabled' ?> required>
            <?= e($slot['name']) ?> (<?= wh_format_time($slot['start_time']) ?>–<?= wh_format_time($slot['end_time']) ?>)
          </label>
          <?php endforeach; ?>
        </div>
        <p class="hint" id="slotAvailHint"></p>
      </div>

      <h3 style="margin:26px 0 18px;">Your Information</h3>
      <div class="form-row">
        <div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Phone Number <span class="req">*</span></label><input type="text" name="phone" value="<?= e($old['phone'] ?? '') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>WhatsApp Number</label><input type="text" name="whatsapp" value="<?= e($old['whatsapp'] ?? '') ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= e($old['address'] ?? '') ?>"></div>
      <div class="form-group"><label>Message / Requirements</label><textarea name="message"><?= e($old['message'] ?? '') ?></textarea></div>

      <button type="submit" class="btn btn-primary btn-block" id="bookingSubmit">Submit Booking Request <i class="fa-solid fa-paper-plane"></i></button>
      <p class="hint" style="text-align:center;margin-top:12px;">We'll check real-time availability the moment you submit.</p>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php if ($allowOnline): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
function checkSlots() {
  var date = document.getElementById('bookingDate').value;
  var hallEl = document.getElementById('hallSelect');
  var hall = hallEl ? hallEl.value : '';
  var hint = document.getElementById('slotAvailHint');
  if (!date || !hall) { return; }
  hint.textContent = 'Checking availability…';
  WH.fetchAvailability(date, hall).then(function (data) {
    if (!data.success || !data.halls || !data.halls.length) { hint.textContent = ''; return; }
    var slotStatus = {};
    data.halls[0].slots.forEach(function (s) { slotStatus[s.id] = s.status; });
    document.querySelectorAll('#slotOptions input[type=radio]').forEach(function (radio) {
      var label = radio.closest('label');
      if (slotStatus[radio.value] === 'booked') {
        label.style.opacity = '.45'; label.style.textDecoration = 'line-through';
        radio.checked = false;
      } else {
        label.style.opacity = '1'; label.style.textDecoration = 'none';
      }
    });
    hint.textContent = 'Greyed-out slots are already booked for this date.';
  });
}
document.getElementById('bookingDate').addEventListener('change', checkSlots);
var hallSelectEl = document.getElementById('hallSelect');
if (hallSelectEl) hallSelectEl.addEventListener('change', checkSlots);
checkSlots();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
