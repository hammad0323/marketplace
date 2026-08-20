<div class="mb-3">
  <label for="f_assets">Current Assets</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_assets" placeholder="e.g. 150000">
</div>
<div class="mb-3">
  <label for="f_liabilities">Current Liabilities</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_liabilities" placeholder="e.g. 75000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Current Ratio</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const assets = tpValidateNumber(document.getElementById('f_assets').value, { label: 'Current assets', min: 0 });
    const liabilities = tpValidateNumber(document.getElementById('f_liabilities').value, { label: 'Current liabilities', min: 0.01 });

    const ratio = assets / liabilities;
    let health = 'Potentially unable to cover short-term obligations';
    if (ratio >= 1 && ratio < 1.5) health = 'Acceptable but tight liquidity';
    if (ratio >= 1.5 && ratio <= 3) health = 'Healthy liquidity position';
    if (ratio > 3) health = 'Very high — assets may be underutilized';

    tpShowResult(ratio.toFixed(2), {
      label: 'Current Ratio — ' + health, raw: ratio.toFixed(4), historyLabel: 'Current Ratio',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
