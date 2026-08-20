<div class="mb-3">
  <label for="f_max_usage">Maximum Daily Usage (units)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_max_usage" placeholder="e.g. 70">
</div>
<div class="mb-3">
  <label for="f_max_lead">Maximum Lead Time (days)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_max_lead" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label for="f_avg_usage">Average Daily Usage (units)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_avg_usage" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_avg_lead">Average Lead Time (days)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_avg_lead" placeholder="e.g. 7">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Safety Stock</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const maxUsage = tpValidateNumber(document.getElementById('f_max_usage').value, { label: 'Max daily usage', min: 0 });
    const maxLead = tpValidateNumber(document.getElementById('f_max_lead').value, { label: 'Max lead time', min: 0 });
    const avgUsage = tpValidateNumber(document.getElementById('f_avg_usage').value, { label: 'Average daily usage', min: 0 });
    const avgLead = tpValidateNumber(document.getElementById('f_avg_lead').value, { label: 'Average lead time', min: 0 });

    const safetyStock = (maxUsage * maxLead) - (avgUsage * avgLead);
    if (safetyStock < 0) throw new Error('With these inputs the maximum scenario is below the average scenario — please double-check the values.');

    tpShowResult(Math.round(safetyStock).toLocaleString() + ' units', {
      label: 'Recommended Safety Stock', raw: safetyStock.toFixed(2), historyLabel: 'Safety Stock',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
