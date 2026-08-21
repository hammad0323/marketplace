<div class="mb-3">
  <label for="f_desired">Desired Order Quantity</label>
  <input type="number" step="1" min="0" class="form-control" id="f_desired" placeholder="e.g. 350">
</div>
<div class="mb-3">
  <label for="f_moq">Supplier's Minimum Order Quantity (MOQ)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_moq" placeholder="e.g. 500">
</div>
<div class="mb-3">
  <label for="f_unit_cost">Unit Cost</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_unit_cost" placeholder="e.g. 4.20">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Check Against MOQ</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const desired = tpValidateNumber(document.getElementById('f_desired').value, { label: 'Desired quantity', min: 0 });
    const moq = tpValidateNumber(document.getElementById('f_moq').value, { label: 'MOQ', min: 1 });
    const unitCost = tpValidateNumber(document.getElementById('f_unit_cost').value || 0, { label: 'Unit cost', min: 0 });

    const meetsMoq = desired >= moq;
    const shortfall = meetsMoq ? 0 : moq - desired;
    const orderQty = Math.max(desired, moq);
    const extraCost = shortfall * unitCost;

    tpShowResult(meetsMoq ? 'Meets MOQ ✓' : `Short by ${shortfall.toLocaleString()} units`, {
      label: meetsMoq
        ? `You can order your desired ${desired.toLocaleString()} units.`
        : `You'll need to order ${orderQty.toLocaleString()} units to meet MOQ (+${tpFormatMoney(extraCost)} extra)`,
      raw: orderQty, historyLabel: 'MOQ Check',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
