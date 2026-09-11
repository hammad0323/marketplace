<div class="mb-3">
  <label for="f_revenue">Total Revenue</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_revenue" placeholder="e.g. 800000">
</div>
<div class="mb-3">
  <label for="f_orders">Number of Orders</label>
  <input type="number" step="1" min="1" class="form-control" id="f_orders" placeholder="e.g. 250">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate AOV</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const revenue = tpValidateNumber(document.getElementById('f_revenue').value, { label: 'Total revenue', min: 0 });
    const orders = tpValidateNumber(document.getElementById('f_orders').value, { label: 'Number of orders', min: 1 });

    const aov = revenue / orders;

    tpShowResult(tpFormatMoney(aov), {
      label: `Average Order Value — ${tpFormatMoney(revenue)} across ${orders} order(s)`,
      raw: aov.toFixed(2), historyLabel: 'Average Order Value',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
