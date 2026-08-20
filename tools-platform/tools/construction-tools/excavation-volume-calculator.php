<div class="mb-3">
  <label for="f_length">Length (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 40">
</div>
<div class="mb-3">
  <label for="f_width">Width (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 25">
</div>
<div class="mb-3">
  <label for="f_depth">Depth (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_depth" placeholder="e.g. 4">
</div>
<div class="mb-3">
  <label for="f_swell">Soil Swell Factor (%)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_swell" value="25">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Excavation Volume</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const depth = tpValidateNumber(document.getElementById('f_depth').value, { label: 'Depth', min: 0 });
    const swell = tpValidateNumber(document.getElementById('f_swell').value || 0, { label: 'Swell factor', min: 0 });

    const bankVolume = length * width * depth;
    const bankCuYd = bankVolume / 27;
    const looseCuYd = bankCuYd * (1 + swell / 100);

    tpShowResult(bankCuYd.toFixed(2) + ' cu yd (bank)', {
      label: `≈ ${looseCuYd.toFixed(2)} cu yd once excavated and loosened (+${swell}% swell)`,
      raw: bankCuYd.toFixed(2), historyLabel: 'Excavation Volume',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
