<div class="mb-3">
  <label for="f_rate">Regular Hourly Rate</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rate" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_hours">Overtime Hours Worked</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_hours" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_multiplier">Overtime Multiplier</label>
  <select id="f_multiplier" class="form-select">
    <option value="1.5" selected>1.5x (Time and a half)</option>
    <option value="2">2x (Double time)</option>
    <option value="2.5">2.5x</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Overtime Pay</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Hourly rate', min: 0 });
    const hours = tpValidateNumber(document.getElementById('f_hours').value, { label: 'Overtime hours', min: 0 });
    const multiplier = parseFloat(document.getElementById('f_multiplier').value);

    const otRate = rate * multiplier;
    const otPay = otRate * hours;

    tpShowResult(tpFormatMoney(otPay), {
      label: 'Overtime Pay', raw: otPay.toFixed(2), historyLabel: 'Overtime Pay',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
