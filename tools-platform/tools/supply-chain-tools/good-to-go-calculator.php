<p class="small text-muted">Check whether you can fulfill an order without dipping into your reserved safety stock.</p>
<div class="mb-3">
  <label for="f_available">Available Stock (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_available" placeholder="e.g. 500">
</div>
<div class="mb-3">
  <label for="f_safety">Safety Stock to Keep in Reserve (units)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_safety" value="0" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_order">Order Quantity Needed (units)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_order" placeholder="e.g. 300">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Check Order Readiness</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const available = tpValidateNumber(document.getElementById('f_available').value, { label: 'Available stock', min: 0 });
    const safety = tpValidateNumber(document.getElementById('f_safety').value || 0, { label: 'Safety stock', min: 0 });
    const order = tpValidateNumber(document.getElementById('f_order').value, { label: 'Order quantity', min: 1 });

    const usable = Math.max(available - safety, 0);

    if (usable >= order) {
      const surplus = usable - order;
      tpShowResult('✅ Good to Go', {
        label: `${surplus.toLocaleString()} unit(s) to spare after this order and your safety reserve`,
        raw: 'yes', historyLabel: 'Order Readiness Check',
      });
    } else {
      const shortfall = order - usable;
      tpShowResult('⚠️ Not Enough Stock', {
        label: `Short by ${shortfall.toLocaleString()} unit(s) once the safety reserve is kept aside`,
        raw: 'no', historyLabel: 'Order Readiness Check',
      });
    }
  } catch (e) { tpShowError(e.message); }
});
</script>
