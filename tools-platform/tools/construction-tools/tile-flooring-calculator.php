<div class="mb-3">
  <label for="f_room_area">Room Area (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_room_area" placeholder="e.g. 200">
</div>
<div class="mb-3">
  <label for="f_tile_area">Tile Size (sq ft per tile)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_tile_area" placeholder="e.g. 4 (24x24in tile)">
</div>
<div class="mb-3">
  <label for="f_wastage">Wastage / Cut Allowance (%)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_wastage" value="10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Tiles Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const roomArea = tpValidateNumber(document.getElementById('f_room_area').value, { label: 'Room area', min: 0 });
    const tileArea = tpValidateNumber(document.getElementById('f_tile_area').value, { label: 'Tile size', min: 0.01 });
    const wastage = tpValidateNumber(document.getElementById('f_wastage').value || 0, { label: 'Wastage', min: 0 });

    const baseTiles = roomArea / tileArea;
    const totalTiles = baseTiles * (1 + wastage / 100);

    tpShowResult(Math.ceil(totalTiles).toLocaleString() + ' tiles', {
      label: `Includes ${wastage}% cut allowance (base need: ${Math.ceil(baseTiles)} tiles)`,
      raw: Math.ceil(totalTiles), historyLabel: 'Tiles Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
