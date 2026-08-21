<div class="mb-3">
  <label for="f_cost">Cost</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_cost" placeholder="e.g. 70">
</div>
<div class="mb-3">
  <label for="f_price">Selling Price</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 100">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Markup &amp; Margin</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Cost', min: 0.01 });
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Selling price', min: 0 });
    if (price < cost) throw new Error('Selling price is below cost — this would be a loss, not a markup.');

    const profit = price - cost;
    const markup = (profit / cost) * 100;
    const margin = (profit / price) * 100;

    tpShowResult(`Markup: ${markup.toFixed(2)}%`, {
      label: `Margin: ${margin.toFixed(2)}% — Profit: ${tpFormatMoney(profit)}`, raw: markup.toFixed(2), historyLabel: 'Markup vs Margin',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
