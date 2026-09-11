<div class="mb-3">
  <label for="f_revenue">Revenue Generated from Ads</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_revenue" placeholder="e.g. 300000">
</div>
<div class="mb-3">
  <label for="f_spend">Ad Spend</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_spend" placeholder="e.g. 50000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate ROAS</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const revenue = tpValidateNumber(document.getElementById('f_revenue').value, { label: 'Ad revenue', min: 0 });
    const spend = tpValidateNumber(document.getElementById('f_spend').value, { label: 'Ad spend', min: 0.01 });

    const roas = revenue / spend;

    tpShowResult(roas.toFixed(2) + 'x', {
      label: `ROAS — every ${tpFormatMoney(1)} spent returned ${tpFormatMoney(roas)} in revenue`,
      raw: roas.toFixed(2), historyLabel: 'ROAS',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
