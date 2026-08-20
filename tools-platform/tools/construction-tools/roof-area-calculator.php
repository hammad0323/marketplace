<div class="mb-3">
  <label for="f_footprint">Building Footprint Area (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_footprint" placeholder="e.g. 1500">
</div>
<div class="mb-3">
  <label for="f_pitch">Roof Pitch</label>
  <select id="f_pitch" class="form-select">
    <option value="1.031">3/12 (flat-ish)</option>
    <option value="1.054">4/12</option>
    <option value="1.083">5/12</option>
    <option value="1.118" selected>6/12 (common)</option>
    <option value="1.158">7/12</option>
    <option value="1.202">8/12</option>
    <option value="1.25">9/12</option>
    <option value="1.302">10/12</option>
    <option value="1.414">12/12 (steep)</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Roof Area</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const footprint = tpValidateNumber(document.getElementById('f_footprint').value, { label: 'Building footprint', min: 0 });
    const pitchFactor = parseFloat(document.getElementById('f_pitch').value);

    const roofArea = footprint * pitchFactor;
    const squares = roofArea / 100; // roofing "squares" = 100 sq ft

    tpShowResult(Math.round(roofArea).toLocaleString() + ' sq ft', {
      label: `≈ ${squares.toFixed(1)} roofing squares`, raw: roofArea.toFixed(2), historyLabel: 'Roof Area',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
