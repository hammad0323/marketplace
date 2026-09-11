<div class="mb-3">
  <label for="f_target">Monthly Sales Target</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_target" placeholder="e.g. 500000">
</div>
<div class="mb-3">
  <label for="f_achieved">Achieved So Far This Month</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_achieved" value="0">
</div>
<div class="mb-3">
  <label for="f_days_left">Working Days Left in the Month</label>
  <input type="number" step="1" min="1" class="form-control" id="f_days_left" placeholder="e.g. 12">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Required Daily Sales</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const target = tpValidateNumber(document.getElementById('f_target').value, { label: 'Sales target', min: 0 });
    const achieved = tpValidateNumber(document.getElementById('f_achieved').value || 0, { label: 'Achieved so far', min: 0 });
    const daysLeft = tpValidateNumber(document.getElementById('f_days_left').value, { label: 'Working days left', min: 1 });

    const remaining = Math.max(target - achieved, 0);
    const dailyNeeded = remaining / daysLeft;
    const pctAchieved = target > 0 ? (achieved / target) * 100 : 0;

    tpShowResult(tpFormatMoney(dailyNeeded) + ' / day', {
      label: `${pctAchieved.toFixed(1)}% achieved — ${tpFormatMoney(remaining)} remaining over ${daysLeft} day(s)`,
      raw: dailyNeeded.toFixed(2), historyLabel: 'Required Daily Sales',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
