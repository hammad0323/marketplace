<div class="mb-3">
  <label for="f_items">Sum of Individual Item Prices</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_items" placeholder="e.g. 3500">
  <small class="text-muted">Add up what each item in the bundle normally sells for.</small>
</div>
<div class="mb-3">
  <label for="f_discount">Bundle Discount (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_discount" placeholder="e.g. 15">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Bundle Price</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const items = tpValidateNumber(document.getElementById('f_items').value, { label: 'Sum of item prices', min: 0 });
    const discount = tpValidateNumber(document.getElementById('f_discount').value, { label: 'Bundle discount', min: 0, max: 100 });

    const savings = items * (discount / 100);
    const bundlePrice = items - savings;

    tpShowResult(tpFormatMoney(bundlePrice), {
      label: `Bundle Price — customer saves ${tpFormatMoney(savings)} vs buying items separately (${tpFormatMoney(items)})`,
      raw: bundlePrice.toFixed(2), historyLabel: 'Bundle Price',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
