<p class="small text-muted">How long your current stock will last — also called stock coverage or days of supply.</p>
<div class="mb-3">
  <label for="f_stock">Current Stock Quantity (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_stock" placeholder="e.g. 600">
</div>
<div class="mb-3">
  <label for="f_daily_usage">Average Daily Usage (units)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_daily_usage" placeholder="e.g. 25">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Stock Holding</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const stock = tpValidateNumber(document.getElementById('f_stock').value, { label: 'Current stock', min: 0 });
    const dailyUsage = tpValidateNumber(document.getElementById('f_daily_usage').value, { label: 'Average daily usage', min: 0.01 });

    const daysOfCoverage = stock / dailyUsage;
    const stockOutDate = new Date(Date.now() + daysOfCoverage * 86400000);

    tpShowResult(Math.floor(daysOfCoverage).toLocaleString() + ' days', {
      label: `Stock Holding — runs out around ${stockOutDate.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}`,
      raw: daysOfCoverage.toFixed(1), historyLabel: 'Stock Holding',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
