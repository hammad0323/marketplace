<div class="mb-3">
  <label for="f_units">Number of Units to Store</label>
  <input type="number" step="1" min="0" class="form-control" id="f_units" placeholder="e.g. 5000">
</div>
<div class="mb-3">
  <label for="f_unit_volume">Volume per Unit (cubic ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_unit_volume" placeholder="e.g. 2.5">
</div>
<div class="mb-3">
  <label for="f_utilization">Expected Space Utilization (%)</label>
  <input type="number" step="1" min="1" max="100" class="form-control" id="f_utilization" value="75">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Warehouse Space Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const units = tpValidateNumber(document.getElementById('f_units').value, { label: 'Units to store', min: 0 });
    const unitVolume = tpValidateNumber(document.getElementById('f_unit_volume').value, { label: 'Volume per unit', min: 0 });
    const utilization = tpValidateNumber(document.getElementById('f_utilization').value, { label: 'Utilization', min: 1, max: 100 });

    const rawVolume = units * unitVolume;
    const neededVolume = rawVolume / (utilization / 100);

    tpShowResult(Math.ceil(neededVolume).toLocaleString() + ' cu ft', {
      label: `Raw storage volume: ${Math.round(rawVolume).toLocaleString()} cu ft, adjusted for ${utilization}% utilization`,
      raw: neededVolume.toFixed(2), historyLabel: 'Warehouse Space',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
