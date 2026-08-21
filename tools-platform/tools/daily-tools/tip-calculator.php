<div class="mb-3">
  <label for="f_bill">Bill Amount</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_bill" placeholder="e.g. 85.50">
</div>
<div class="mb-3">
  <label for="f_tip">Tip Percentage</label>
  <input type="number" step="1" min="0" max="100" class="form-control" id="f_tip" value="18">
</div>
<div class="mb-3">
  <label for="f_people">Number of People</label>
  <input type="number" step="1" min="1" class="form-control" id="f_people" value="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Tip</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const bill = tpValidateNumber(document.getElementById('f_bill').value, { label: 'Bill amount', min: 0 });
    const tipPct = tpValidateNumber(document.getElementById('f_tip').value, { label: 'Tip percentage', min: 0, max: 100 });
    const people = tpValidateNumber(document.getElementById('f_people').value, { label: 'Number of people', min: 1 });

    const tipAmount = bill * (tipPct / 100);
    const total = bill + tipAmount;
    const perPerson = total / people;

    tpShowResult(tpFormatMoney(perPerson) + ' / person', {
      label: `Total: ${tpFormatMoney(total)} (Tip: ${tpFormatMoney(tipAmount)})`, raw: perPerson.toFixed(2), historyLabel: 'Tip Split',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
