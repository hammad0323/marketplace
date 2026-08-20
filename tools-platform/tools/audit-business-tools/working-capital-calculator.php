<div class="mb-3">
  <label for="f_assets">Current Assets</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_assets" placeholder="e.g. 250000">
</div>
<div class="mb-3">
  <label for="f_liabilities">Current Liabilities</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_liabilities" placeholder="e.g. 150000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Working Capital</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const assets = tpValidateNumber(document.getElementById('f_assets').value, { label: 'Current assets', min: 0 });
    const liabilities = tpValidateNumber(document.getElementById('f_liabilities').value, { label: 'Current liabilities', min: 0 });

    const workingCapital = assets - liabilities;
    const health = workingCapital >= 0 ? 'Positive — able to cover short-term obligations' : 'Negative — potential short-term liquidity risk';

    tpShowResult('$' + workingCapital.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), {
      label: health, raw: workingCapital.toFixed(2), historyLabel: 'Working Capital',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
