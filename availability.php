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
    <p>Pick a date to see which halls and time slots are free before you book.</p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:760px;">
    <?php if (!$showAvailability): ?>
      <div class="alert alert-info">Live availability display is currently turned off for this website. Please <a href="<?= e(BASE_URL) ?>/contact">contact us</a> to check availability, or submit an <a href="<?= e(BASE_URL) ?>/booking">online booking request</a> and we'll confirm it for you.</div>
    <?php else: ?>
    <div class="form-card reveal">
      <div class="form-row">
        <div class="form-group">
          <label for="availDate">Event Date</label>
          <input type="date" id="availDate" min="<?= e(date('Y-m-d')) ?>" value="<?= e(wh_input_get('date', date('Y-m-d'))) ?>">
        </div>
        <div class="form-group">
          <label for="availHall">Hall (optional)</label>
          <select id="availHall">
            <option value="">All Halls</option>
            <?php foreach ($halls as $hall): ?><option value="<?= (int) $hall['id'] ?>"><?= e($hall['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="button" class="btn btn-primary" id="availCheckBtn"><i class="fa-solid fa-magnifying-glass"></i> Check Availability</button>
    </div>
    <div id="availResult" style="margin-top:32px;"></div>
    <?php endif; ?>
  </div>
</section>
<?php if ($showAvailability): ?>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
function runAvailCheck() {
  var date = document.getElementById('availDate').value;
  var hall = document.getElementById('availHall').value;
  var box = document.getElementById('availResult');
  if (!date) { return; }
  box.innerHTML = '<p class="hint">Checking…</p>';
  WH.fetchAvailability(date, hall).then(function (data) { WH.renderAvailabilityTable(box, data); });
}
document.getElementById('availCheckBtn').addEventListener('click', runAvailCheck);
document.getElementById('availDate').addEventListener('change', runAvailCheck);
document.getElementById('availHall').addEventListener('change', runAvailCheck);
runAvailCheck();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
