<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$code = wh_input_get('code');
$booking = wh_fetch_one(
    "SELECT b.*, h.name AS hall_name, ts.name AS slot_name, ts.start_time, ts.end_time, et.name AS event_type_name, c.name AS customer_name
     FROM bookings b JOIN halls h ON h.id=b.hall_id JOIN time_slots ts ON ts.id=b.time_slot_id
     LEFT JOIN event_types et ON et.id=b.event_type_id JOIN customers c ON c.id=b.customer_id
     WHERE b.booking_code=? AND b.business_id=?",
    'si',
    [$code, $businessId]
);
if (!$booking) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$isOwner = in_array($code, $_SESSION['recent_booking_codes'] ?? [], true);

$pageTitle = 'Booking Confirmation';
$activeNav = '';
require __DIR__ . '/header.php';
$statusLabel = ['pending' => 'Pending Confirmation', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'completed' => 'Completed', 'hold' => 'On Hold'];
?>
<section class="section" style="padding-top:64px;">
  <div class="container" style="max-width:640px;">
    <div class="card reveal" style="padding:40px;text-align:center;">
      <i class="fa-solid fa-circle-check" style="font-size:3rem;color:var(--success);"></i>
      <h1 style="margin-top:18px;font-size:1.8rem;">Booking Request Received</h1>
      <p>Thank you<?= $isOwner ? ', ' . e($booking['customer_name']) : '' ?>! Here are your booking details.</p>

      <table class="simple" style="text-align:left;margin-top:24px;">
        <tr><th>Booking ID</th><td><strong><?= e($booking['booking_code']) ?></strong></td></tr>
        <tr><th>Hall</th><td><?= e($booking['hall_name']) ?></td></tr>
        <tr><th>Date</th><td><?= wh_format_date($booking['booking_date']) ?></td></tr>
        <tr><th>Time</th><td><?= e($booking['slot_name']) ?> (<?= wh_format_time($booking['start_time']) ?>–<?= wh_format_time($booking['end_time']) ?>)</td></tr>
        <?php if ($booking['event_type_name']): ?><tr><th>Event</th><td><?= e($booking['event_type_name']) ?></td></tr><?php endif; ?>
        <tr><th>Status</th><td><span class="pill <?= $booking['booking_status'] === 'confirmed' ? 'pill-available' : 'pill-hidden' ?>"><?= e($statusLabel[$booking['booking_status']] ?? ucfirst($booking['booking_status'])) ?></span></td></tr>
        <?php if ($isOwner): ?>
        <tr><th>Guests</th><td><?= (int) $booking['guests'] ?></td></tr>
        <tr><th>Total</th><td><?= wh_format_money($booking['final_total']) ?></td></tr>
        <tr><th>Advance Required</th><td><?= wh_format_money($booking['advance_required']) ?></td></tr>
        <?php endif; ?>
      </table>

      <p class="hint" style="margin-top:24px;">Our team will review your request and confirm your booking shortly. Save your Booking ID for reference.</p>
      <a href="<?= e(BASE_URL) ?>/" class="btn btn-primary" style="margin-top:10px;">Back to Home</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
