<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$halls = wh_get_halls($businessId, true);
$showAvailability = wh_setting_bool('show_public_availability', true, $businessId);

$pageTitle = 'Check Availability';
$seoPageKey = 'availability';
$activeNav = 'availability';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Check Availability</h1>
    <p>Browse the calendar to see which dates are open, or pick a date to see hall-by-hall, slot-by-slot detail.</p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:900px;">
    <?php if (!$showAvailability): ?>
      <div class="alert alert-info">Live availability display is currently turned off for this website. Please <a href="<?= e(BASE_URL) ?>/contact">contact us</a> to check availability, or submit an <a href="<?= e(BASE_URL) ?>/booking">online booking request</a> and we'll confirm it for you.</div>
    <?php else: ?>
    <div class="wh-calendar-card reveal">
      <div class="form-group" style="max-width:280px;margin-bottom:20px;">
        <label for="availHall">Filter by Hall</label>
        <select id="availHall">
          <option value="">All Halls</option>
          <?php foreach ($halls as $hall): ?><option value="<?= (int) $hall['id'] ?>"><?= e($hall['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="cal-legend">
        <span><span class="dot" style="background:var(--success);"></span> Available</span>
        <span><span class="dot" style="background:var(--warning);"></span> Partially Booked</span>
        <span><span class="dot" style="background:var(--danger);"></span> Fully Booked</span>
      </div>
      <div id="publicCalendar"></div>
    </div>
    <div id="availResult" style="margin-top:28px;"></div>
    <?php endif; ?>
  </div>
</section>
<?php if ($showAvailability): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/calendar-widget.js"></script>
<script>
var availHallSelect = document.getElementById('availHall');
var availResultBox = document.getElementById('availResult');

function showDateDetail(dateStr) {
  availResultBox.innerHTML = '<p class="hint">Checking ' + dateStr + '…</p>';
  WH.fetchAvailability(dateStr, availHallSelect.value).then(function (data) {
    availResultBox.innerHTML = '<h3 style="margin-bottom:14px;">' + dateStr + '</h3>';
    var wrap = document.createElement('div');
    WH.renderAvailabilityTable(wrap, data);
    availResultBox.appendChild(wrap);
    availResultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
}

WH.initCalendar(document.getElementById('publicCalendar'), {
  hallSelect: availHallSelect,
  onDateClick: showDateDetail
});

var preselectedDate = <?= json_encode(wh_input_get('date', '')) ?>;
if (preselectedDate) { showDateDetail(preselectedDate); }
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
