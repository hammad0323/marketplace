<div class="mb-3">
  <label for="f_previous">Previous Period Sales</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_previous" placeholder="e.g. 400000">
</div>
<div class="mb-3">
  <label for="f_current">Current Period Sales</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_current" placeholder="e.g. 480000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Growth</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const previous = tpValidateNumber(document.getElementById('f_previous').value, { label: 'Previous period sales', min: 0.01 });
    const current = tpValidateNumber(document.getElementById('f_current').value, { label: 'Current period sales', min: 0 });

    const growth = ((current - previous) / previous) * 100;
    const direction = growth >= 0 ? 'growth' : 'decline';

    tpShowResult((growth >= 0 ? '+' : '') + growth.toFixed(2) + '%', {
      label: `Sales ${direction} from ${tpFormatMoney(previous)} to ${tpFormatMoney(current)}`,
      raw: growth.toFixed(2), historyLabel: 'Sales Growth',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
