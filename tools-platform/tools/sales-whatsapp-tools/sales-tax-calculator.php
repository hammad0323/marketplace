<div class="mb-3">
  <label for="f_amount">Sale Amount</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_amount" placeholder="e.g. 10000">
</div>
<div class="mb-3">
  <label for="f_rate">Sales Tax / GST / VAT Rate (%)</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_rate" placeholder="e.g. 17">
  <small class="text-muted">Use your applicable rate — e.g. Pakistan's standard sales tax rate is commonly 17% (verify the current rate for your province/sector with FBR).</small>
</div>
<div class="mb-3">
  <label for="f_mode">Amount Entered Is</label>
  <select class="form-select" id="f_mode">
    <option value="exclusive">Tax-exclusive (tax needs to be added)</option>
    <option value="inclusive">Tax-inclusive (tax is already included)</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Tax</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const amount = tpValidateNumber(document.getElementById('f_amount').value, { label: 'Sale amount', min: 0 });
    const rate = tpValidateNumber(document.getElementById('f_rate').value, { label: 'Tax rate', min: 0, max: 100 });
    const mode = document.getElementById('f_mode').value;

    let taxAmount, netAmount, grossAmount;
    if (mode === 'exclusive') {
      netAmount = amount;
      taxAmount = amount * (rate / 100);
      grossAmount = netAmount + taxAmount;
    } else {
      grossAmount = amount;
      netAmount = amount / (1 + rate / 100);
      taxAmount = grossAmount - netAmount;
    }

    tpShowResult(tpFormatMoney(taxAmount), {
      label: `Tax Amount — Net: ${tpFormatMoney(netAmount)}, Gross (with tax): ${tpFormatMoney(grossAmount)}`,
      raw: taxAmount.toFixed(2), historyLabel: 'Sales Tax',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
