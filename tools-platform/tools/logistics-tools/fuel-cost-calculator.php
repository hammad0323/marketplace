<div class="mb-3">
  <label for="f_distance">Distance (miles)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_distance" placeholder="e.g. 250">
</div>
<div class="mb-3">
  <label for="f_efficiency">Fuel Efficiency (miles per gallon)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_efficiency" placeholder="e.g. 22">
</div>
<div class="mb-3">
  <label for="f_price">Fuel Price (per gallon)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 3.75">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Fuel Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const distance = tpValidateNumber(document.getElementById('f_distance').value, { label: 'Distance', min: 0 });
    const efficiency = tpValidateNumber(document.getElementById('f_efficiency').value, { label: 'Fuel efficiency', min: 0.01 });
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Fuel price', min: 0 });

    const gallonsNeeded = distance / efficiency;
    const cost = gallonsNeeded * price;

    tpShowResult(tpFormatMoney(cost), {
      label: `≈ ${gallonsNeeded.toFixed(1)} gallons for ${distance} miles`, raw: cost.toFixed(2), historyLabel: 'Fuel Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
