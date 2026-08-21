<div class="mb-3">
  <label for="f_principal">Initial Principal</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_principal" placeholder="e.g. 10000">
</div>
<div class="mb-3">
  <label for="f_rate">Annual Interest Rate (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rate" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_times">Compounded</label>
  <select id="f_times" class="form-select">
    <option value="1">Annually</option>
    <option value="2">Semi-Annually</option>
    <option value="4">Quarterly</option>
    <option value="12" selected>Monthly</option>
    <option value="365">Daily</option>
  </select>
</div>
<div class="mb-3">
  <label for="f_years">Years</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_years" placeholder="e.g. 10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const principal = tpValidateNumber(document.getElementById('f_principal').value, { label: 'Principal', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Interest rate', min: 0 });
    const n = parseFloat(document.getElementById('f_times').value);
    const years = tpValidateNumber(document.getElementById('f_years').value, { label: 'Years', min: 0 });

    const amount = principal * Math.pow(1 + (rate / 100) / n, n * years);
    const interestEarned = amount - principal;

    tpShowResult(tpFormatMoney(amount), {
      label: `Future Value — Interest Earned: ${tpFormatMoney(interestEarned)}`,
      raw: amount.toFixed(2), historyLabel: 'Compound Interest',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
