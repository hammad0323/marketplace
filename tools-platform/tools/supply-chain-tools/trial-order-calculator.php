<p class="small text-muted">Estimate a sensible first order quantity for a new or trial product, based on your expected demand plus a safety buffer since trial demand is unproven.</p>
<div class="mb-3">
  <label for="f_weekly_demand">Expected Weekly Demand (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_weekly_demand" placeholder="e.g. 40">
</div>
<div class="mb-3">
  <label for="f_weeks">Trial Period (weeks)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_weeks" placeholder="e.g. 8">
</div>
<div class="mb-3">
  <label for="f_buffer">Safety Buffer (%)</label>
  <input type="number" step="1" min="0" max="200" class="form-control" id="f_buffer" value="20">
  <small class="text-muted">Extra cushion on top of expected demand, since trial demand isn't proven yet.</small>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Trial Order Quantity</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const weeklyDemand = tpValidateNumber(document.getElementById('f_weekly_demand').value, { label: 'Expected weekly demand', min: 0 });
    const weeks = tpValidateNumber(document.getElementById('f_weeks').value, { label: 'Trial period', min: 1 });
    const bufferPct = tpValidateNumber(document.getElementById('f_buffer').value || 0, { label: 'Safety buffer', min: 0, max: 200 });

    const baseDemand = weeklyDemand * weeks;
    const trialOrderQty = baseDemand * (1 + bufferPct / 100);

    tpShowResult(Math.ceil(trialOrderQty).toLocaleString() + ' units', {
      label: `Trial Order Quantity — ${baseDemand.toLocaleString()} base demand + ${bufferPct}% buffer over ${weeks} week(s)`,
      raw: trialOrderQty.toFixed(2), historyLabel: 'Trial Order Quantity',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
