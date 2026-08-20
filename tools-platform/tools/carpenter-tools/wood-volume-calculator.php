<div class="mb-3">
  <label for="f_length">Length (in)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 96">
</div>
<div class="mb-3">
  <label for="f_width">Width (in)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_thickness">Thickness (in)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_thickness" placeholder="e.g. 1.5">
</div>
<div class="mb-3">
  <label for="f_qty">Quantity</label>
  <input type="number" step="1" min="1" class="form-control" id="f_qty" value="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Wood Volume</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const thickness = tpValidateNumber(document.getElementById('f_thickness').value, { label: 'Thickness', min: 0 });
    const qty = tpValidateNumber(document.getElementById('f_qty').value, { label: 'Quantity', min: 1 });

    const volumeCuInEach = length * width * thickness;
    const totalCuIn = volumeCuInEach * qty;
    const totalCuFt = totalCuIn / 1728;

    tpShowResult(totalCuFt.toFixed(3) + ' cu ft', {
      label: `${totalCuIn.toFixed(1)} cu in total across ${qty} piece(s)`, raw: totalCuFt.toFixed(4), historyLabel: 'Wood Volume',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
