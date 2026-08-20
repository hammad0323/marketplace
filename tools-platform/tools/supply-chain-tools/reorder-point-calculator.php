<div class="mb-3">
  <label for="f_avg_usage">Average Daily Usage (units)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_avg_usage" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_lead_time">Average Lead Time (days)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_lead_time" placeholder="e.g. 7">
</div>
<div class="mb-3">
  <label for="f_safety_stock">Safety Stock (units)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_safety_stock" placeholder="e.g. 100" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Reorder Point</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const avgUsage = tpValidateNumber(document.getElementById('f_avg_usage').value, { label: 'Average daily usage', min: 0 });
    const leadTime = tpValidateNumber(document.getElementById('f_lead_time').value, { label: 'Lead time', min: 0 });
    const safetyStock = tpValidateNumber(document.getElementById('f_safety_stock').value || 0, { label: 'Safety stock', min: 0 });

    const rop = (avgUsage * leadTime) + safetyStock;

    tpShowResult(Math.round(rop).toLocaleString() + ' units', {
      label: 'Reorder Point', raw: rop.toFixed(2), historyLabel: 'Reorder Point',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
