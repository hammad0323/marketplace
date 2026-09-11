<div class="mb-3">
  <label for="f_weight">Package Weight (kg)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_weight" placeholder="e.g. 2.5">
</div>
<div class="mb-3">
  <label for="f_base_rate">Base Rate (first kg)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_base_rate" placeholder="e.g. 150">
</div>
<div class="mb-3">
  <label for="f_extra_rate">Rate per Additional kg</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_extra_rate" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_cod_fee">COD Handling Fee (optional, % of order value)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cod_fee" value="0">
</div>
<div class="mb-3">
  <label for="f_order_value">Order Value (only needed if COD fee % is set)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_order_value" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Courier Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const weight = tpValidateNumber(document.getElementById('f_weight').value, { label: 'Weight', min: 0.01 });
    const baseRate = tpValidateNumber(document.getElementById('f_base_rate').value, { label: 'Base rate', min: 0 });
    const extraRate = tpValidateNumber(document.getElementById('f_extra_rate').value, { label: 'Extra rate per kg', min: 0 });
    const codFeePct = tpValidateNumber(document.getElementById('f_cod_fee').value || 0, { label: 'COD fee %', min: 0 });
    const orderValue = tpValidateNumber(document.getElementById('f_order_value').value || 0, { label: 'Order value', min: 0 });

    const extraWeight = Math.max(weight - 1, 0);
    const shippingCost = baseRate + (extraWeight * extraRate);
    const codFee = orderValue * (codFeePct / 100);
    const totalCost = shippingCost + codFee;

    tpShowResult(tpFormatMoney(totalCost), {
      label: `Courier Cost for ${weight}kg` + (codFee > 0 ? ` — Shipping: ${tpFormatMoney(shippingCost)} + COD fee: ${tpFormatMoney(codFee)}` : ''),
      raw: totalCost.toFixed(2), historyLabel: 'Courier Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
