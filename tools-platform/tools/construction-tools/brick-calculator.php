<div class="mb-3">
  <label for="f_wall_area">Wall Area (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_wall_area" placeholder="e.g. 300">
</div>
<div class="mb-3">
  <label for="f_bricks_per_sqft">Bricks per sq ft (standard modular brick ≈ 6.86)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_bricks_per_sqft" value="6.86">
</div>
<div class="mb-3">
  <label for="f_waste">Waste Allowance (%)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_waste" value="5">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Bricks Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const wallArea = tpValidateNumber(document.getElementById('f_wall_area').value, { label: 'Wall area', min: 0 });
    const bricksPerSqft = tpValidateNumber(document.getElementById('f_bricks_per_sqft').value, { label: 'Bricks per sq ft', min: 0.01 });
    const waste = tpValidateNumber(document.getElementById('f_waste').value || 0, { label: 'Waste allowance', min: 0 });

    const baseBricks = wallArea * bricksPerSqft;
    const totalBricks = baseBricks * (1 + waste / 100);

    tpShowResult(Math.ceil(totalBricks).toLocaleString() + ' bricks', {
      label: `Includes ${waste}% waste allowance`, raw: Math.ceil(totalBricks), historyLabel: 'Bricks Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
