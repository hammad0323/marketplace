<div class="mb-3">
  <label for="f_gain">Total Gain from Investment</label>
  <input type="number" step="0.01" class="form-control" id="f_gain" placeholder="e.g. 15000">
</div>
<div class="mb-3">
  <label for="f_cost">Total Cost of Investment</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_cost" placeholder="e.g. 10000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate ROI</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const gain = tpValidateNumber(document.getElementById('f_gain').value, { label: 'Total gain' });
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Total cost', min: 0.01 });

    const netProfit = gain - cost;
    const roi = (netProfit / cost) * 100;

    tpShowResult(roi.toFixed(2) + '%', {
      label: `Net profit: ${tpFormatMoney(netProfit)}`, raw: roi.toFixed(2), historyLabel: 'ROI',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
