<div class="mb-3">
  <label for="f_revenue">Revenue</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_revenue" placeholder="e.g. 10000">
</div>
<div class="mb-3">
  <label for="f_cost">Cost of Goods Sold</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cost" placeholder="e.g. 7000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Margin</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const revenue = tpValidateNumber(document.getElementById('f_revenue').value, { label: 'Revenue', min: 0.01 });
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Cost', min: 0 });

    const profit = revenue - cost;
    const margin = (profit / revenue) * 100;
    const markup = cost > 0 ? (profit / cost) * 100 : null;

    tpShowResult(margin.toFixed(2) + '%', {
      label: `Profit Margin — Profit: ${tpFormatMoney(profit)}` + (markup !== null ? `, Markup: ${markup.toFixed(2)}%` : ''),
      raw: margin.toFixed(2), historyLabel: 'Profit Margin',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
