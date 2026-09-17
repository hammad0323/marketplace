<?php
require __DIR__ . '/../config.php';
wh_require_page_access('dashboard');

$businessId = wh_current_business_id();
$stats = wh_dashboard_stats($businessId);
$upcoming = wh_upcoming_events($businessId, 6);
$halls = wh_get_halls($businessId);
$pendingRequests = wh_fetch_all(
    "SELECT b.*, h.name AS hall_name, c.name AS customer_name FROM bookings b
     JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
     WHERE b.business_id=? AND b.booking_status='pending' ORDER BY b.booking_date ASC LIMIT 6",
    'i',
    [$businessId]
);

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/header.php';
?>
<div class="stat-grid">
  <div class="admin-card stat-card"><div class="icon icon-primary"><i class="fa-solid fa-building-columns"></i></div><div class="label">Total Halls</div><div class="value"><?= $stats['total_halls'] ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-info"><i class="fa-solid fa-list-check"></i></div><div class="label">Total Bookings</div><div class="value"><?= $stats['total_bookings'] ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-success"><i class="fa-solid fa-calendar-day"></i></div><div class="label">Today's Bookings</div><div class="value"><?= $stats['todays_bookings'] ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-warning"><i class="fa-solid fa-hourglass-half"></i></div><div class="label">Pending Requests</div><div class="value"><?= $stats['pending_requests'] ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-success"><i class="fa-solid fa-sack-dollar"></i></div><div class="label">Total Revenue Received</div><div class="value"><?= wh_format_money($stats['total_revenue']) ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-warning"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="label">Pending Payments</div><div class="value"><?= wh_format_money($stats['pending_payments']) ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-info"><i class="fa-solid fa-coins"></i></div><div class="label">Advance This Month</div><div class="value"><?= wh_format_money($stats['advance_received']) ?></div></div>
  <div class="admin-card stat-card"><div class="icon icon-primary"><i class="fa-solid fa-calendar"></i></div><div class="label">This Month's Bookings</div><div class="value"><?= $stats['month_bookings'] ?></div></div>
</div>

<div class="two-col">
  <div class="admin-card">
    <div class="card-head">
      <h3>Booking Calendar</h3>
      <select id="calHallFilter" style="width:auto;">
        <option value="">All Halls</option>
        <?php foreach ($halls as $hall): ?><option value="<?= (int) $hall['id'] ?>"><?= e($hall['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div id="dashCalendar"></div>
    <div id="dashDateDetail" style="margin-top:18px;"></div>
  </div>

  <div>
    <div class="admin-card">
      <div class="card-head"><h3>Upcoming Events</h3><a href="<?= e(BASE_URL) ?>/admin/bookings.php" class="btn btn-light btn-sm">View All</a></div>
      <?php if (!$upcoming): ?><p class="hint">No upcoming events.</p><?php endif; ?>
      <?php foreach ($upcoming as $b): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--a-border);">
        <strong><?= wh_format_date($b['booking_date']) ?></strong> — <?= e($b['hall_name']) ?> — <?= e($b['slot_name']) ?><br>
        <span class="hint"><?= e($b['event_type_name'] ?? 'Event') ?> · <?= (int) $b['guests'] ?> guests · Balance: <?= wh_format_money($b['balance']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-card">
      <div class="card-head"><h3>Pending Requests</h3></div>
      <?php if (!$pendingRequests): ?><p class="hint">No pending requests. Great job!</p><?php endif; ?>
      <?php foreach ($pendingRequests as $b): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--a-border);display:flex;justify-content:space-between;align-items:center;">
        <div>
          <strong><?= e($b['customer_name']) ?></strong> — <?= e($b['hall_name']) ?><br>
          <span class="hint"><?= wh_format_date($b['booking_date']) ?></span>
        </div>
        <a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $b['id'] ?>" class="btn btn-light btn-sm">Review</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="<?= e(BASE_URL) ?>/assets/js/calendar-widget.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
WH.initCalendar(document.getElementById('dashCalendar'), {
  hallSelect: document.getElementById('calHallFilter'),
  onDateClick: function (dateStr) {
    var box = document.getElementById('dashDateDetail');
    box.innerHTML = '<p class="hint">Loading ' + dateStr + '…</p>';
    var hallId = document.getElementById('calHallFilter').value;
    WH.fetchAvailability(dateStr, hallId).then(function (data) {
      box.innerHTML = '<h4 style="margin-bottom:10px;">' + dateStr + '</h4>';
      var wrap = document.createElement('div');
      WH.renderAvailabilityTable(wrap, data);
      box.appendChild(wrap);
    });
  }
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
