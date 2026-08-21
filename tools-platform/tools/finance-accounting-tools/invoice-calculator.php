<div class="mb-3">
  <label for="f_subtotal">Subtotal</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_subtotal" placeholder="e.g. 1000">
</div>
<div class="mb-3">
  <label for="f_tax_rate">Tax Rate (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_tax_rate" value="0">
</div>
<div class="mb-3">
  <label for="f_discount">Discount (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_discount" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Invoice Total</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const subtotal = tpValidateNumber(document.getElementById('f_subtotal').value, { label: 'Subtotal', min: 0 });
    const taxRate = tpValidateNumber(document.getElementById('f_tax_rate').value || 0, { label: 'Tax rate', min: 0 });
    const discountRate = tpValidateNumber(document.getElementById('f_discount').value || 0, { label: 'Discount', min: 0, max: 100 });

    const discountAmount = subtotal * (discountRate / 100);
    const afterDiscount = subtotal - discountAmount;
    const taxAmount = afterDiscount * (taxRate / 100);
    const total = afterDiscount + taxAmount;

    tpShowResult(tpFormatMoney(total), {
      label: `Discount: -${tpFormatMoney(discountAmount)}, Tax: +${tpFormatMoney(taxAmount)}`, raw: total.toFixed(2), historyLabel: 'Invoice Total',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
