<?php
require __DIR__ . '/../config.php';
wh_require_page_access('date-search');
$businessId = wh_current_business_id();
$halls = wh_get_halls($businessId);

$pageTitle = 'Date Search';
$activePage = 'date-search';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <h3 style="margin-bottom:16px;">Quick Date Availability Search</h3>
  <p class="hint">Find out instantly which hall is available on which date and time slot — no page reload.</p>
  <div class="filter-bar">
    <div class="form-group"><label>Date</label><input type="date" id="searchDate" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="form-group"><label>Hall (optional)</label>
      <select id="searchHall"><option value="">All Halls</option><?php foreach ($halls as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
    </div>
    <button type="button" class="btn btn-primary" id="searchBtn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
  </div>
  <div id="searchResults" style="margin-top:20px;"></div>
</div>
<script src="<?= e(BASE_URL) ?>/assets/js/availability.js"></script>
<script>
function runSearch() {
  var date = document.getElementById('searchDate').value;
  var hall = document.getElementById('searchHall').value;
  var box = document.getElementById('searchResults');
  if (!date) return;
  box.innerHTML = '<p class="hint">Searching…</p>';
  WH.fetchAvailability(date, hall).then(function (data) { WH.renderAvailabilityTable(box, data); });
}
document.getElementById('searchBtn').addEventListener('click', runSearch);
document.getElementById('searchDate').addEventListener('change', runSearch);
document.getElementById('searchHall').addEventListener('change', runSearch);
runSearch();
</script>
<?php require __DIR__ . '/footer.php'; ?>
