<div class="mb-3">
  <label for="f_area">Wall Area (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_area" placeholder="e.g. 400">
</div>
<div class="mb-3">
  <label for="f_coats">Number of Coats</label>
  <input type="number" step="1" min="1" class="form-control" id="f_coats" value="2">
</div>
<div class="mb-3">
  <label for="f_coverage">Paint Coverage (sq ft per gallon)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_coverage" value="350">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Paint Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const area = tpValidateNumber(document.getElementById('f_area').value, { label: 'Wall area', min: 0 });
    const coats = tpValidateNumber(document.getElementById('f_coats').value, { label: 'Number of coats', min: 1 });
    const coverage = tpValidateNumber(document.getElementById('f_coverage').value, { label: 'Coverage per gallon', min: 1 });

    const gallons = (area * coats) / coverage;

    tpShowResult(Math.ceil(gallons * 4) / 4 + ' gallons', {
      label: `Rounded up to the nearest quart-gallon for ${coats} coat(s) over ${area} sq ft`,
      raw: gallons.toFixed(2), historyLabel: 'Paint Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
