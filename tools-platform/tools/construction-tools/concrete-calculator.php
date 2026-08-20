<div class="mb-3">
  <label for="f_length">Length (feet)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_width">Width (feet)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label for="f_thickness">Thickness (inches)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_thickness" placeholder="e.g. 4">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Concrete Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const thicknessIn = tpValidateNumber(document.getElementById('f_thickness').value, { label: 'Thickness', min: 0 });

    const thicknessFt = thicknessIn / 12;
    const cubicFeet = length * width * thicknessFt;
    const cubicYards = cubicFeet / 27;
    const bags80lb = cubicYards * 45; // approx 45 x 80lb bags per cubic yard

    tpShowResult(cubicYards.toFixed(2) + ' cu yd', {
      label: `Concrete Needed — ≈${Math.ceil(bags80lb)} bags (80lb) or ${cubicFeet.toFixed(1)} cu ft`,
      raw: cubicYards.toFixed(3), historyLabel: 'Concrete Volume',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
