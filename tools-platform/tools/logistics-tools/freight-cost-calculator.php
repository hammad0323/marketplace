<div class="mb-3">
  <label for="f_weight">Chargeable Weight (kg)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_weight" placeholder="e.g. 120">
</div>
<div class="mb-3">
  <label for="f_rate">Rate per kg</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rate" placeholder="e.g. 4.50">
</div>
<div class="mb-3">
  <label for="f_fees">Additional Fixed Fees (handling, customs, etc.)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_fees" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Freight Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const weight = tpValidateNumber(document.getElementById('f_weight').value, { label: 'Chargeable weight', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Rate per kg', min: 0 });
    const fees = tpValidateNumber(document.getElementById('f_fees').value || 0, { label: 'Additional fees', min: 0 });

    const total = (weight * rate) + fees;

    tpShowResult(tpFormatMoney(total), {
      label: `${weight}kg × ${tpFormatMoney(rate)}/kg + ${tpFormatMoney(fees)} fees`, raw: total.toFixed(2), historyLabel: 'Freight Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
