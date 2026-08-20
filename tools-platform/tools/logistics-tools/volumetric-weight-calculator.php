<div class="mb-3">
  <label for="f_length">Length (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 40">
</div>
<div class="mb-3">
  <label for="f_width">Width (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 30">
</div>
<div class="mb-3">
  <label for="f_height">Height (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_height" placeholder="e.g. 25">
</div>
<div class="mb-3">
  <label for="f_divisor">Carrier Divisor (air freight typically 5000, courier 5000/6000)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_divisor" value="5000">
</div>
<div class="mb-3">
  <label for="f_actual">Actual Weight (kg)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_actual" placeholder="e.g. 8">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Chargeable Weight</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const l = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const w = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const h = tpValidateNumber(document.getElementById('f_height').value, { label: 'Height', min: 0 });
    const divisor = tpValidateNumber(document.getElementById('f_divisor').value, { label: 'Divisor', min: 1 });
    const actual = tpValidateNumber(document.getElementById('f_actual').value, { label: 'Actual weight', min: 0 });

    const volumetric = (l * w * h) / divisor;
    const chargeable = Math.max(volumetric, actual);

    tpShowResult(chargeable.toFixed(2) + ' kg', {
      label: `Volumetric: ${volumetric.toFixed(2)} kg vs Actual: ${actual.toFixed(2)} kg — carrier bills the higher one`,
      raw: chargeable.toFixed(3), historyLabel: 'Chargeable Weight',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
