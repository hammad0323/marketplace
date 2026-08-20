<div class="mb-3">
  <label for="f_area">Total Project Area (sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_area" placeholder="e.g. 320">
</div>
<div class="mb-3">
  <label for="f_sheet_size">Sheet Size (sq ft — standard 4x8 = 32)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_sheet_size" value="32">
</div>
<div class="mb-3">
  <label for="f_waste">Waste Allowance (%)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_waste" value="10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Sheets Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const area = tpValidateNumber(document.getElementById('f_area').value, { label: 'Project area', min: 0 });
    const sheetSize = tpValidateNumber(document.getElementById('f_sheet_size').value, { label: 'Sheet size', min: 0.01 });
    const waste = tpValidateNumber(document.getElementById('f_waste').value || 0, { label: 'Waste allowance', min: 0 });

    const sheets = (area / sheetSize) * (1 + waste / 100);

    tpShowResult(Math.ceil(sheets) + ' sheets', {
      label: `Includes ${waste}% waste allowance`, raw: Math.ceil(sheets), historyLabel: 'Plywood Sheets',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
