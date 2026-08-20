<p class="text-muted small">Enter hours worked each day this week.</p>
<div id="tpTimesheetRows" class="row g-2 mb-2">
  <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1"><?= $day ?></label>
      <input type="number" step="0.25" min="0" class="form-control timesheet-day" placeholder="0">
    </div>
  <?php endforeach; ?>
</div>
<div class="mb-3">
  <label for="f_overtime_threshold">Overtime Threshold (hours/week)</label>
  <input type="number" step="0.5" min="0" class="form-control" id="f_overtime_threshold" value="40">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Weekly Hours</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dayInputs = document.querySelectorAll('.timesheet-day');
    let total = 0;
    dayInputs.forEach((el) => { total += tpValidateNumber(el.value || 0, { label: 'Daily hours', min: 0 }); });
    const threshold = tpValidateNumber(document.getElementById('f_overtime_threshold').value, { label: 'Overtime threshold', min: 0 });

    const regular = Math.min(total, threshold);
    const overtime = Math.max(0, total - threshold);

    tpShowResult(total.toFixed(2) + ' hrs', {
      label: `Regular: ${regular.toFixed(2)} hrs — Overtime: ${overtime.toFixed(2)} hrs`,
      raw: total.toFixed(2), historyLabel: 'Weekly Hours',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
