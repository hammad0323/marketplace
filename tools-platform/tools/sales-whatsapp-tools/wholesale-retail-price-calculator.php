<div class="mb-3">
  <label for="f_cost">Product Cost</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cost" placeholder="e.g. 500">
</div>
<div class="mb-3">
  <label for="f_wholesale_margin">Wholesale Margin (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_wholesale_margin" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_retail_margin">Retail Margin (%)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_retail_margin" placeholder="e.g. 60">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Prices</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Product cost', min: 0 });
    const wholesaleMargin = tpValidateNumber(document.getElementById('f_wholesale_margin').value, { label: 'Wholesale margin', min: 0 });
    const retailMargin = tpValidateNumber(document.getElementById('f_retail_margin').value, { label: 'Retail margin', min: 0 });

    const wholesalePrice = cost * (1 + wholesaleMargin / 100);
    const retailPrice = cost * (1 + retailMargin / 100);

    tpShowResult(tpFormatMoney(wholesalePrice) + ' / ' + tpFormatMoney(retailPrice), {
      label: `Wholesale price (left) vs Retail price (right) from a ${tpFormatMoney(cost)} cost`,
      raw: `Wholesale: ${wholesalePrice.toFixed(2)}, Retail: ${retailPrice.toFixed(2)}`, historyLabel: 'Wholesale/Retail Pricing',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
