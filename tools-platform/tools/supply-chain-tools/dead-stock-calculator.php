<div class="mb-3">
  <label for="f_qty">Current Stock Quantity (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_qty" placeholder="e.g. 250">
</div>
<div class="mb-3">
  <label for="f_cost">Unit Cost</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cost" placeholder="e.g. 12.50">
</div>
<div class="mb-3">
  <label for="f_days_since_sale">Days Since Last Sale</label>
  <input type="number" step="1" min="0" class="form-control" id="f_days_since_sale" placeholder="e.g. 120">
</div>
<div class="mb-3">
  <label for="f_threshold">Dead Stock Threshold (days)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_threshold" value="90">
  <small class="text-muted">Items with no sale for at least this many days are flagged as dead stock.</small>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Check Dead Stock</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const qty = tpValidateNumber(document.getElementById('f_qty').value, { label: 'Stock quantity', min: 0 });
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Unit cost', min: 0 });
    const daysSinceSale = tpValidateNumber(document.getElementById('f_days_since_sale').value, { label: 'Days since last sale', min: 0 });
    const threshold = tpValidateNumber(document.getElementById('f_threshold').value, { label: 'Dead stock threshold', min: 1 });

    const capitalTiedUp = qty * cost;
    const isDead = daysSinceSale >= threshold;

    tpShowResult(tpFormatMoney(capitalTiedUp), {
      label: (isDead ? '⚠️ Dead Stock' : '✓ Active Stock') + ` — no sale in ${daysSinceSale} day(s), threshold is ${threshold}`,
      raw: capitalTiedUp.toFixed(2), historyLabel: 'Dead Stock Check',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
