<div class="mb-3">
  <label for="f_net_income">Net Income</label>
  <input type="number" step="0.01" class="form-control" id="f_net_income" placeholder="e.g. 120000">
</div>
<div class="mb-3">
  <label for="f_interest">Interest Expense</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_interest" placeholder="e.g. 15000">
</div>
<div class="mb-3">
  <label for="f_taxes">Taxes</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_taxes" placeholder="e.g. 30000">
</div>
<div class="mb-3">
  <label for="f_da">Depreciation &amp; Amortization</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_da" placeholder="e.g. 25000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate EBITDA</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const netIncome = tpValidateNumber(document.getElementById('f_net_income').value, { label: 'Net income' });
    const interest = tpValidateNumber(document.getElementById('f_interest').value, { label: 'Interest expense', min: 0 });
    const taxes = tpValidateNumber(document.getElementById('f_taxes').value, { label: 'Taxes', min: 0 });
    const da = tpValidateNumber(document.getElementById('f_da').value, { label: 'Depreciation & amortization', min: 0 });

    const ebitda = netIncome + interest + taxes + da;

    tpShowResult(tpFormatMoney(ebitda), {
      label: 'EBITDA', raw: ebitda.toFixed(2), historyLabel: 'EBITDA',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
