<div class="mb-3">
  <label for="f_distance">Trip Distance (miles)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_distance" placeholder="e.g. 400">
</div>
<div class="mb-3">
  <label for="f_mpg">Vehicle Fuel Efficiency (MPG)</label>
  <input type="number" step="0.1" min="0.1" class="form-control" id="f_mpg" placeholder="e.g. 24">
</div>
<div class="mb-3">
  <label for="f_fuel_price">Fuel Price (per gallon)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_fuel_price" placeholder="e.g. 3.60">
</div>
<div class="mb-3">
  <label for="f_extras">Tolls, Food &amp; Other Costs</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_extras" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Total Trip Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const distance = tpValidateNumber(document.getElementById('f_distance').value, { label: 'Trip distance', min: 0 });
    const mpg = tpValidateNumber(document.getElementById('f_mpg').value, { label: 'Fuel efficiency', min: 0.1 });
    const fuelPrice = tpValidateNumber(document.getElementById('f_fuel_price').value, { label: 'Fuel price', min: 0 });
    const extras = tpValidateNumber(document.getElementById('f_extras').value || 0, { label: 'Extra costs', min: 0 });

    const gallonsNeeded = distance / mpg;
    const fuelCost = gallonsNeeded * fuelPrice;
    const total = fuelCost + extras;

    tpShowResult(tpFormatMoney(total), {
      label: `Fuel: ${tpFormatMoney(fuelCost)} + Extras: ${tpFormatMoney(extras)}`, raw: total.toFixed(2), historyLabel: 'Trip Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
