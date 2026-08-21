<div class="mb-3">
  <label for="f_budget">Budgeted / Planned Amount</label>
  <input type="number" step="0.01" class="form-control" id="f_budget" placeholder="e.g. 50000">
</div>
<div class="mb-3">
  <label for="f_actual">Actual Amount</label>
  <input type="number" step="0.01" class="form-control" id="f_actual" placeholder="e.g. 47500">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Variance</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const budget = tpValidateNumber(document.getElementById('f_budget').value, { label: 'Budgeted amount' });
    const actual = tpValidateNumber(document.getElementById('f_actual').value, { label: 'Actual amount' });
    if (budget === 0) throw new Error('Budgeted amount cannot be zero — percentage variance is undefined.');

    const variance = actual - budget;
    const variancePct = (variance / Math.abs(budget)) * 100;
    const direction = variance > 0 ? 'over budget' : variance < 0 ? 'under budget' : 'on budget';

    tpShowResult(tpFormatMoney(variance), {
      label: `${Math.abs(variancePct).toFixed(2)}% ${direction}`, raw: variance.toFixed(2), historyLabel: 'Variance',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
