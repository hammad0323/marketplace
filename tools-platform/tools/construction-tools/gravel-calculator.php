<div class="mb-3">
  <label for="f_length">Area Length (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_width">Area Width (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label for="f_depth">Gravel Depth (inches)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_depth" placeholder="e.g. 4">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Gravel Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const depthIn = tpValidateNumber(document.getElementById('f_depth').value, { label: 'Depth', min: 0 });

    const depthFt = depthIn / 12;
    const cubicFeet = length * width * depthFt;
    const cubicYards = cubicFeet / 27;
    const tons = cubicYards * 1.4; // ≈1.4 tons per cubic yard for typical gravel

    tpShowResult(cubicYards.toFixed(2) + ' cu yd', {
      label: `≈ ${tons.toFixed(2)} tons (${cubicFeet.toFixed(1)} cu ft)`, raw: cubicYards.toFixed(3), historyLabel: 'Gravel Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
