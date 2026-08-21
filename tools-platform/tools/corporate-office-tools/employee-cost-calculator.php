<div class="mb-3">
  <label for="f_salary">Annual Base Salary</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_salary" placeholder="e.g. 60000">
</div>
<div class="mb-3">
  <label for="f_benefits">Benefits (% of salary — health, retirement, etc.)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_benefits" value="20">
</div>
<div class="mb-3">
  <label for="f_payroll_tax">Employer Payroll Tax (%)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_payroll_tax" value="7.65">
</div>
<div class="mb-3">
  <label for="f_overhead">Other Overhead (annual, e.g. equipment, office space)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_overhead" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate True Employee Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const salary = tpValidateNumber(document.getElementById('f_salary').value, { label: 'Base salary', min: 0 });
    const benefitsPct = tpValidateNumber(document.getElementById('f_benefits').value || 0, { label: 'Benefits %', min: 0 });
    const payrollTaxPct = tpValidateNumber(document.getElementById('f_payroll_tax').value || 0, { label: 'Payroll tax %', min: 0 });
    const overhead = tpValidateNumber(document.getElementById('f_overhead').value || 0, { label: 'Other overhead', min: 0 });

    const benefitsCost = salary * (benefitsPct / 100);
    const payrollTaxCost = salary * (payrollTaxPct / 100);
    const totalCost = salary + benefitsCost + payrollTaxCost + overhead;
    const loadMultiplier = totalCost / salary;

    tpShowResult(tpFormatMoney(totalCost), {
      label: `Load multiplier: ${loadMultiplier.toFixed(2)}x base salary`, raw: totalCost.toFixed(2), historyLabel: 'Employee Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
