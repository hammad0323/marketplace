<div class="mb-3">
  <label for="f_demand">Annual Demand (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_demand" placeholder="e.g. 12000">
</div>
<div class="mb-3">
  <label for="f_order_cost">Ordering Cost per Order</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_order_cost" placeholder="e.g. 75">
</div>
<div class="mb-3">
  <label for="f_holding_cost">Holding Cost per Unit / Year</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_holding_cost" placeholder="e.g. 3.50">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate EOQ</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const demand = tpValidateNumber(document.getElementById('f_demand').value, { label: 'Annual demand', min: 0 });
    const orderCost = tpValidateNumber(document.getElementById('f_order_cost').value, { label: 'Ordering cost', min: 0 });
    const holdingCost = tpValidateNumber(document.getElementById('f_holding_cost').value, { label: 'Holding cost', min: 0.01 });

    const eoq = Math.sqrt((2 * demand * orderCost) / holdingCost);
    const ordersPerYear = demand / eoq;

    tpShowResult(Math.round(eoq).toLocaleString() + ' units', {
      label: 'Economic Order Quantity (≈' + ordersPerYear.toFixed(1) + ' orders/year)',
      raw: eoq.toFixed(2), historyLabel: 'EOQ',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
