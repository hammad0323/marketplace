<div class="mb-3">
  <label for="f_liabilities">Total Liabilities</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_liabilities" placeholder="e.g. 300000">
</div>
<div class="mb-3">
  <label for="f_equity">Total Shareholders' Equity</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_equity" placeholder="e.g. 200000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Debt-to-Equity</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const liabilities = tpValidateNumber(document.getElementById('f_liabilities').value, { label: 'Total liabilities', min: 0 });
    const equity = tpValidateNumber(document.getElementById('f_equity').value, { label: "Shareholders' equity", min: 0.01 });

    const ratio = liabilities / equity;

    tpShowResult(ratio.toFixed(2), {
      label: 'Debt-to-Equity Ratio', raw: ratio.toFixed(4), historyLabel: 'D/E Ratio',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
