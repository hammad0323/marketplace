<div class="mb-3">
  <label for="f_principal">Principal</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_principal" placeholder="e.g. 10000">
</div>
<div class="mb-3">
  <label for="f_rate">Annual Interest Rate (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rate" placeholder="e.g. 5">
</div>
<div class="mb-3">
  <label for="f_years">Time Period (years)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_years" placeholder="e.g. 3">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Simple Interest</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const principal = tpValidateNumber(document.getElementById('f_principal').value, { label: 'Principal', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Interest rate', min: 0 });
    const years = tpValidateNumber(document.getElementById('f_years').value, { label: 'Time period', min: 0 });

    const interest = (principal * rate * years) / 100;
    const total = principal + interest;

    tpShowResult(tpFormatMoney(interest), {
      label: `Simple Interest — Total Payable: ${tpFormatMoney(total)}`,
      raw: interest.toFixed(2), historyLabel: 'Simple Interest',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
