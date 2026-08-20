<div class="mb-3">
  <label for="f_start_odo">Starting Odometer</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_start_odo" placeholder="e.g. 45000">
</div>
<div class="mb-3">
  <label for="f_end_odo">Ending Odometer</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_end_odo" placeholder="e.g. 45320">
</div>
<div class="mb-3">
  <label for="f_fuel_used">Fuel Used (gallons)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_fuel_used" placeholder="e.g. 12.5">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Mileage</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const startOdo = tpValidateNumber(document.getElementById('f_start_odo').value, { label: 'Starting odometer', min: 0 });
    const endOdo = tpValidateNumber(document.getElementById('f_end_odo').value, { label: 'Ending odometer', min: 0 });
    const fuelUsed = tpValidateNumber(document.getElementById('f_fuel_used').value, { label: 'Fuel used', min: 0.01 });
    if (endOdo < startOdo) throw new Error('Ending odometer must be greater than starting odometer.');

    const distance = endOdo - startOdo;
    const mpg = distance / fuelUsed;

    tpShowResult(mpg.toFixed(2) + ' MPG', {
      label: `${distance.toFixed(1)} miles on ${fuelUsed} gallons`, raw: mpg.toFixed(2), historyLabel: 'Mileage',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
