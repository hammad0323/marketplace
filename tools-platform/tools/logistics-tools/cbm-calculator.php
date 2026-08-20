<div class="mb-3">
  <label for="f_length">Length (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 120">
</div>
<div class="mb-3">
  <label for="f_width">Width (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 100">
</div>
<div class="mb-3">
  <label for="f_height">Height (cm)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_height" placeholder="e.g. 100">
</div>
<div class="mb-3">
  <label for="f_qty">Quantity of Boxes</label>
  <input type="number" step="1" min="1" class="form-control" id="f_qty" value="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate CBM</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const l = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const w = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const h = tpValidateNumber(document.getElementById('f_height').value, { label: 'Height', min: 0 });
    const qty = tpValidateNumber(document.getElementById('f_qty').value, { label: 'Quantity', min: 1 });

    const cbmEach = (l * w * h) / 1_000_000;
    const totalCbm = cbmEach * qty;

    tpShowResult(totalCbm.toFixed(3) + ' m³', {
      label: `${cbmEach.toFixed(3)} m³ per box × ${qty}`, raw: totalCbm.toFixed(4), historyLabel: 'CBM',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
