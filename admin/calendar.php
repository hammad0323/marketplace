<?php
require __DIR__ . '/../config.php';
wh_require_page_access('calendar');
$businessId = wh_current_business_id();
$halls = wh_get_halls($businessId);

$pageTitle = 'Booking Calendar';
$activePage = 'calendar';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Booking Calendar</h3>
    <select id="calHallFilter" style="width:auto;">
      <option value="">All Halls</option>
      <?php foreach ($halls as $hall): ?><option value="<?= (int) $hall['id'] ?>"><?= e($hall['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="badge-row" style="margin-bottom:18px;">
    <span class="state-dot state-available" style="display:inline-flex;padding:4px 10px;border-radius:999px;font-size:.75rem;">Available</span>
    <span class="state-dot state-partial" style="display:inline-flex;padding:4px 10px;border-radius:999px;font-size:.75rem;">Partially Booked</span>
    <span class="state-dot state-full" style="display:inline-flex;padding:4px 10px;border-radius:999px;font-size:.75rem;">Fully Booked</span>
  </div>
  <div id="fullCalendar"></div>
</div>

<div class="admin-card" id="dateDetailCard" style="display:none;">
  <h3 id="dateDetailTitle"></h3>
  <div id="dateDetailBody"></div>
</div>

<script src="<?= e(BASE_URL) ?>/assets/js/calendar-widget.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
WH.initCalendar(document.getElementById('fullCalendar'), {
  hallSelect: document.getElementById('calHallFilter'),
  onDateClick: function (dateStr) {
    var card = document.getElementById('dateDetailCard');
    var body = document.getElementById('dateDetailBody');
    card.style.display = 'block';
    document.getElementById('dateDetailTitle').textContent = 'Availability — ' + dateStr;
    body.innerHTML = '<p class="hint">Loading…</p>';
    var hallId = document.getElementById('calHallFilter').value;
    WH.fetchAvailability(dateStr, hallId).then(function (data) { WH.renderAvailabilityTable(body, data); });
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
