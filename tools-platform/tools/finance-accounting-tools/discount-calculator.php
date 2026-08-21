<div class="mb-3">
  <label for="f_price">Original Price</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 120">
</div>
<div class="mb-3">
  <label for="f_discount">Discount (%)</label>
  <input type="number" step="0.1" min="0" max="100" class="form-control" id="f_discount" placeholder="e.g. 25">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Final Price</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Original price', min: 0 });
    const discount = tpValidateNumber(document.getElementById('f_discount').value, { label: 'Discount', min: 0, max: 100 });

    const savings = price * (discount / 100);
    const finalPrice = price - savings;

    tpShowResult(tpFormatMoney(finalPrice), {
      label: `You save ${tpFormatMoney(savings)}`, raw: finalPrice.toFixed(2), historyLabel: 'Discounted Price',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
