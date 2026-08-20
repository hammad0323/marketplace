<div class="mb-3">
  <label for="f_cogs">Cost of Goods Sold (annual)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cogs" placeholder="e.g. 500000">
</div>
<div class="mb-3">
  <label for="f_avg_inventory">Average Inventory Value</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_avg_inventory" placeholder="e.g. 100000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Turnover</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const cogs = tpValidateNumber(document.getElementById('f_cogs').value, { label: 'COGS', min: 0 });
    const avgInventory = tpValidateNumber(document.getElementById('f_avg_inventory').value, { label: 'Average inventory', min: 0.01 });

    const turnover = cogs / avgInventory;
    const daysInInventory = 365 / turnover;

    tpShowResult(turnover.toFixed(2) + 'x / year', {
      label: 'Inventory Turnover (≈' + daysInInventory.toFixed(0) + ' days of inventory on hand)',
      raw: turnover.toFixed(4), historyLabel: 'Inventory Turnover',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
