<div class="mb-3">
  <label for="f_sides">Number of Sides on the Frame/Polygon</label>
  <input type="number" step="1" min="3" max="24" class="form-control" id="f_sides" placeholder="e.g. 4 (rectangle frame)">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Miter Angle</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const sides = tpValidateNumber(document.getElementById('f_sides').value, { label: 'Number of sides', min: 3, max: 24 });

    const interiorAngle = ((sides - 2) * 180) / sides;
    const miterAngle = (180 - interiorAngle) / 2;

    tpShowResult(miterAngle.toFixed(2) + '°', {
      label: `Miter saw angle per corner for a regular ${sides}-sided frame`, raw: miterAngle.toFixed(3), historyLabel: 'Miter Angle',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
