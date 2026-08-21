<div class="mb-3">
  <label for="f_principal">Loan Amount</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_principal" placeholder="e.g. 250000">
</div>
<div class="mb-3">
  <label for="f_rate">Annual Interest Rate (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rate" placeholder="e.g. 7.5">
</div>
<div class="mb-3">
  <label for="f_tenure">Loan Tenure (months)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_tenure" placeholder="e.g. 360">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate EMI</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const principal = tpValidateNumber(document.getElementById('f_principal').value, { label: 'Loan amount', min: 0 });
    const annualRate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Interest rate', min: 0 });
    const months = tpValidateNumber(document.getElementById('f_tenure').value, { label: 'Tenure', min: 1 });

    const monthlyRate = annualRate / 12 / 100;
    let emi;
    if (monthlyRate === 0) {
      emi = principal / months;
    } else {
      const factor = Math.pow(1 + monthlyRate, months);
      emi = (principal * monthlyRate * factor) / (factor - 1);
    }
    const totalPayment = emi * months;
    const totalInterest = totalPayment - principal;

    tpShowResult(tpFormatMoney(emi), {
      label: `Monthly EMI — Total Interest: ${tpFormatMoney(totalInterest, 0)}, Total Payment: ${tpFormatMoney(totalPayment, 0)}`,
      raw: emi.toFixed(2), historyLabel: 'Loan EMI',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
