<div class="mb-3">
  <label for="f_sales">Total Sales Amount</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_sales" placeholder="e.g. 250000">
</div>
<div class="mb-3">
  <label for="f_rate">Commission Rate (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_rate" placeholder="e.g. 5">
</div>
<div class="mb-3">
  <label for="f_base">Fixed Base Pay (optional)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_base" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Commission</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const sales = tpValidateNumber(document.getElementById('f_sales').value, { label: 'Total sales', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Commission rate', min: 0, max: 100 });
    const base = tpValidateNumber(document.getElementById('f_base').value || 0, { label: 'Base pay', min: 0 });

    const commission = sales * (rate / 100);
    const total = commission + base;

    tpShowResult(tpFormatMoney(commission), {
      label: `Commission on ${tpFormatMoney(sales)} at ${rate}%` + (base > 0 ? ` — Total with base pay: ${tpFormatMoney(total)}` : ''),
      raw: commission.toFixed(2), historyLabel: 'Sales Commission',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
