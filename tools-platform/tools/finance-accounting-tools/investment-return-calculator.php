<div class="mb-3">
  <label for="f_initial">Initial Investment</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_initial" placeholder="e.g. 10000">
</div>
<div class="mb-3">
  <label for="f_final">Final Value</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_final" placeholder="e.g. 16000">
</div>
<div class="mb-3">
  <label for="f_years">Investment Period (years)</label>
  <input type="number" step="0.1" min="0.01" class="form-control" id="f_years" placeholder="e.g. 5">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Annualized Return</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const initial = tpValidateNumber(document.getElementById('f_initial').value, { label: 'Initial investment', min: 0.01 });
    const final = tpValidateNumber(document.getElementById('f_final').value, { label: 'Final value', min: 0 });
    const years = tpValidateNumber(document.getElementById('f_years').value, { label: 'Investment period', min: 0.01 });

    const totalReturn = ((final - initial) / initial) * 100;
    const cagr = (Math.pow(final / initial, 1 / years) - 1) * 100;

    tpShowResult(cagr.toFixed(2) + '% / year', {
      label: `Total return: ${totalReturn.toFixed(2)}% over ${years} year(s)`, raw: cagr.toFixed(4), historyLabel: 'Annualized Return (CAGR)',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
