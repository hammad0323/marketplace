<div class="mb-3">
  <label for="f_start">Start Date</label>
  <input type="date" class="form-control" id="f_start">
</div>
<div class="mb-3">
  <label for="f_end">End Date</label>
  <input type="date" class="form-control" id="f_end">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Business Days</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const startVal = document.getElementById('f_start').value;
    const endVal = document.getElementById('f_end').value;
    if (!startVal || !endVal) throw new Error('Please choose both a start date and an end date.');

    const start = new Date(startVal + 'T00:00:00');
    const end = new Date(endVal + 'T00:00:00');
    if (isNaN(start) || isNaN(end)) throw new Error('One of the dates is invalid.');
    if (end < start) throw new Error('End date must be on or after the start date.');

    let count = 0;
    const cursor = new Date(start);
    while (cursor <= end) {
      const day = cursor.getDay();
      if (day !== 0 && day !== 6) count++;
      cursor.setDate(cursor.getDate() + 1);
    }

    tpShowResult(count.toLocaleString() + (count === 1 ? ' day' : ' days'), {
      label: 'Business Days (excludes weekends)', raw: count, historyLabel: 'Business Days',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
