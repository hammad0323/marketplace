<div class="mb-3">
  <label for="f_start">Start Date</label>
  <input type="date" class="form-control" id="f_start">
</div>
<div class="mb-3">
  <label for="f_end">End Date</label>
  <input type="date" class="form-control" id="f_end">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Difference</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const startVal = document.getElementById('f_start').value;
    const endVal = document.getElementById('f_end').value;
    if (!startVal || !endVal) throw new Error('Please choose both dates.');

    const start = new Date(startVal + 'T00:00:00');
    const end = new Date(endVal + 'T00:00:00');
    const laterDate = end >= start ? end : start;
    const earlierDate = end >= start ? start : end;

    let years = laterDate.getFullYear() - earlierDate.getFullYear();
    let months = laterDate.getMonth() - earlierDate.getMonth();
    let days = laterDate.getDate() - earlierDate.getDate();
    if (days < 0) { months--; days += new Date(laterDate.getFullYear(), laterDate.getMonth(), 0).getDate(); }
    if (months < 0) { years--; months += 12; }

    const totalDays = Math.round((laterDate - earlierDate) / 86400000);

    tpShowResult(totalDays.toLocaleString() + ' days', {
      label: `${years}y ${months}m ${days}d (${Math.round(totalDays/7)} weeks)`, raw: totalDays, historyLabel: 'Date Difference',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
