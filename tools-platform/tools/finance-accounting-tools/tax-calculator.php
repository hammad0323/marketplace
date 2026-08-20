<div class="mb-3">
  <label for="f_income">Taxable Income</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_income" placeholder="e.g. 65000">
</div>
<div class="mb-3">
  <label for="f_rate">Effective Tax Rate (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_rate" placeholder="e.g. 18.5">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Tax Owed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const income = tpValidateNumber(document.getElementById('f_income').value, { label: 'Taxable income', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Tax rate', min: 0, max: 100 });

    const tax = income * (rate / 100);
    const net = income - tax;

    tpShowResult('$' + tax.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), {
      label: `Net income after tax: $${net.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`, raw: tax.toFixed(2), historyLabel: 'Tax Owed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
