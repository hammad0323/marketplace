<div class="mb-3">
  <label for="f_sale">Product Sale Price</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_sale" placeholder="e.g. 2500">
</div>
<div class="mb-3">
  <label for="f_cost">Product Cost</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cost" placeholder="e.g. 1200">
</div>
<div class="mb-3">
  <label for="f_delivery">Courier / Delivery Charge</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_delivery" placeholder="e.g. 200">
</div>
<div class="mb-3">
  <label for="f_return_rate">Estimated Return/Refusal Rate (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_return_rate" value="0">
  <small class="text-muted">% of COD orders typically refused/returned — the delivery charge on those is usually a loss.</small>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate COD Profit</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const sale = tpValidateNumber(document.getElementById('f_sale').value, { label: 'Sale price', min: 0 });
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Product cost', min: 0 });
    const delivery = tpValidateNumber(document.getElementById('f_delivery').value, { label: 'Delivery charge', min: 0 });
    const returnRate = tpValidateNumber(document.getElementById('f_return_rate').value || 0, { label: 'Return rate', min: 0, max: 100 });

    const profitPerDelivered = sale - cost - delivery;
    // Expected profit per order placed, accounting for the return rate: a
    // returned order loses the cost+delivery on the trip out (a common
    // simplification — actual return-shipping costs vary by courier).
    const expectedProfit = (profitPerDelivered * (1 - returnRate / 100)) - ((cost + delivery) * (returnRate / 100));

    tpShowResult(tpFormatMoney(profitPerDelivered), {
      label: `Profit per delivered order` + (returnRate > 0 ? ` — expected profit per order placed (with ${returnRate}% returns): ${tpFormatMoney(expectedProfit)}` : ''),
      raw: profitPerDelivered.toFixed(2), historyLabel: 'COD Profit',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
