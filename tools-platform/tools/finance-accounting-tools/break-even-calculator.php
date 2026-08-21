<div class="mb-3">
  <label for="f_fixed">Total Fixed Costs</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_fixed" placeholder="e.g. 20000">
</div>
<div class="mb-3">
  <label for="f_price">Selling Price per Unit</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_variable">Variable Cost per Unit</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_variable" placeholder="e.g. 30">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Break-Even Point</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const fixed = tpValidateNumber(document.getElementById('f_fixed').value, { label: 'Fixed costs', min: 0 });
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Selling price', min: 0.01 });
    const variable = tpValidateNumber(document.getElementById('f_variable').value, { label: 'Variable cost', min: 0 });

    const contribution = price - variable;
    if (contribution <= 0) throw new Error('Selling price must be greater than the variable cost per unit.');

    const units = fixed / contribution;
    const revenue = units * price;

    tpShowResult(Math.ceil(units).toLocaleString() + ' units', {
      label: `Break-Even Point — Revenue: ${tpFormatMoney(revenue, 0)}`,
      raw: units.toFixed(2), historyLabel: 'Break-Even Units',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
