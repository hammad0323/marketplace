<div class="mb-3">
  <label for="f_resign_date">Resignation / Notice Date</label>
  <input type="date" class="form-control" id="f_resign_date">
</div>
<div class="mb-3">
  <label for="f_notice_days">Notice Period (days)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_notice_days" placeholder="e.g. 30">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Last Working Day</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dateVal = document.getElementById('f_resign_date').value;
    if (!dateVal) throw new Error('Please select the notice date.');
    const noticeDays = tpValidateNumber(document.getElementById('f_notice_days').value, { label: 'Notice period', min: 0 });

    const start = new Date(dateVal + 'T00:00:00');
    const lastDay = new Date(start);
    lastDay.setDate(lastDay.getDate() + noticeDays);

    const formatted = lastDay.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

    tpShowResult(formatted, {
      label: `${noticeDays} days from ${start.toLocaleDateString()}`, raw: lastDay.toISOString().slice(0, 10), historyLabel: 'Last Working Day',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
