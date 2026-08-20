<div class="mb-3">
  <label for="f_length">Wall Length (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 30">
</div>
<div class="mb-3">
  <label for="f_height">Wall Height (ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_height" placeholder="e.g. 9">
</div>
<div class="mb-3">
  <label for="f_openings">Total Openings Area — doors/windows (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_openings" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Net Wall Area</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Wall length', min: 0 });
    const height = tpValidateNumber(document.getElementById('f_height').value, { label: 'Wall height', min: 0 });
    const openings = tpValidateNumber(document.getElementById('f_openings').value || 0, { label: 'Openings area', min: 0 });

    const grossArea = length * height;
    const netArea = grossArea - openings;
    if (netArea < 0) throw new Error('Openings area cannot exceed the gross wall area.');

    tpShowResult(netArea.toFixed(2) + ' sq ft', {
      label: `Gross area: ${grossArea.toFixed(2)} sq ft − ${openings} sq ft openings`, raw: netArea.toFixed(2), historyLabel: 'Net Wall Area',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
