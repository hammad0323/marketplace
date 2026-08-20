<div class="mb-3">
  <label for="f_volume">Total Concrete Volume (cu ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_volume" placeholder="e.g. 100">
</div>
<div class="mb-3">
  <label for="f_ratio">Mix Ratio (Cement : Sand : Aggregate)</label>
  <select id="f_ratio" class="form-select">
    <option value="1,2,4">1:2:4 (general purpose)</option>
    <option value="1,1.5,3">1:1.5:3 (standard structural)</option>
    <option value="1,3,6">1:3:6 (mass concrete)</option>
    <option value="1,1,2">1:1:2 (high strength)</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Sand Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const volume = tpValidateNumber(document.getElementById('f_volume').value, { label: 'Concrete volume', min: 0 });
    const [c, s, a] = document.getElementById('f_ratio').value.split(',').map(Number);
    const totalParts = c + s + a;

    const dryVolume = volume * 1.54;
    const sandVolume = dryVolume * (s / totalParts);
    const sandTons = (sandVolume * 100) / 2000; // ≈100 lb/cu ft bulk density

    tpShowResult(sandVolume.toFixed(2) + ' cu ft', {
      label: `≈ ${sandTons.toFixed(2)} tons for a ${document.getElementById('f_ratio').value.replace(/,/g, ':')} mix`,
      raw: sandVolume.toFixed(2), historyLabel: 'Sand Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
