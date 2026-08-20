<div class="mb-3">
  <label for="f_gross">Gross Salary</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_gross" placeholder="e.g. 5000">
</div>
<div class="mb-3">
  <label for="f_tax">Tax Rate (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_tax" placeholder="e.g. 15">
</div>
<div class="mb-3">
  <label for="f_deductions">Other Deductions</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_deductions" placeholder="e.g. 150" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Net Salary</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const gross = tpValidateNumber(document.getElementById('f_gross').value, { label: 'Gross salary', min: 0 });
    const taxRate = tpValidateNumber(document.getElementById('f_tax').value, { label: 'Tax rate', min: 0, max: 100 });
    const deductions = tpValidateNumber(document.getElementById('f_deductions').value || 0, { label: 'Other deductions', min: 0 });

    const taxAmount = gross * (taxRate / 100);
    const net = gross - taxAmount - deductions;
    if (net < 0) throw new Error('Deductions and tax exceed gross salary — please check your inputs.');

    tpShowResult('$' + net.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), {
      label: 'Estimated Net Salary', raw: net.toFixed(2), historyLabel: 'Net Salary',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
