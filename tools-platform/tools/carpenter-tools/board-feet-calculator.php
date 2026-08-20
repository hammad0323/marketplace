<div class="mb-3">
  <label for="f_thickness">Thickness (inches)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_thickness" placeholder="e.g. 1">
</div>
<div class="mb-3">
  <label for="f_width">Width (inches)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_width" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_length">Length (feet)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_length" placeholder="e.g. 8">
</div>
<div class="mb-3">
  <label for="f_quantity">Quantity of Boards</label>
  <input type="number" step="1" min="1" class="form-control" id="f_quantity" value="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Board Feet</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const thickness = tpValidateNumber(document.getElementById('f_thickness').value, { label: 'Thickness', min: 0 });
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 0 });
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 0 });
    const quantity = tpValidateNumber(document.getElementById('f_quantity').value, { label: 'Quantity', min: 1 });

    const boardFeetEach = (thickness * width * length) / 12;
    const total = boardFeetEach * quantity;

    tpShowResult(total.toFixed(2) + ' bd ft', {
      label: `${boardFeetEach.toFixed(2)} bd ft per board × ${quantity}`, raw: total.toFixed(3), historyLabel: 'Board Feet',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
