<div class="mb-3">
  <label for="f_price">Total Price</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 60000">
</div>
<div class="mb-3">
  <label for="f_down">Down Payment</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_down" value="0">
</div>
<div class="mb-3">
  <label for="f_months">Number of Installments (months)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_months" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_markup">Installment Markup (%, optional)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_markup" value="0">
  <small class="text-muted">Extra % added for offering installments instead of full upfront payment.</small>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Monthly Installment</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Total price', min: 0 });
    const down = tpValidateNumber(document.getElementById('f_down').value || 0, { label: 'Down payment', min: 0, max: price });
    const months = tpValidateNumber(document.getElementById('f_months').value, { label: 'Number of installments', min: 1 });
    const markup = tpValidateNumber(document.getElementById('f_markup').value || 0, { label: 'Markup', min: 0 });

    const remaining = (price - down) * (1 + markup / 100);
    const monthly = remaining / months;

    tpShowResult(tpFormatMoney(monthly) + ' / month', {
      label: `${months} installment(s) after ${tpFormatMoney(down)} down payment — total payable: ${tpFormatMoney(remaining + down)}`,
      raw: monthly.toFixed(2), historyLabel: 'Monthly Installment',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
