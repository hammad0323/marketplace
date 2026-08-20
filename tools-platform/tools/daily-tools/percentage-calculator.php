<div class="mb-3">
  <label>Calculation Type</label>
  <select id="f_mode" class="form-select">
    <option value="of">X% of Y</option>
    <option value="is_what_percent">X is what % of Y</option>
    <option value="change">% change from X to Y</option>
  </select>
</div>
<div class="mb-3">
  <label for="f_x">X</label>
  <input type="number" step="any" class="form-control" id="f_x" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_y">Y</label>
  <input type="number" step="any" class="form-control" id="f_y" placeholder="e.g. 150">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const mode = document.getElementById('f_mode').value;
    const x = tpValidateNumber(document.getElementById('f_x').value, { label: 'X' });
    const y = tpValidateNumber(document.getElementById('f_y').value, { label: 'Y' });

    let result, label;
    if (mode === 'of') {
      result = (x / 100) * y;
      label = `${x}% of ${y}`;
    } else if (mode === 'is_what_percent') {
      if (y === 0) throw new Error('Y cannot be zero.');
      result = (x / y) * 100;
      label = `${x} is this % of ${y}`;
    } else {
      if (x === 0) throw new Error('X (the starting value) cannot be zero for a % change calculation.');
      result = ((y - x) / Math.abs(x)) * 100;
      label = `% change from ${x} to ${y}`;
    }

    const suffix = mode === 'of' ? '' : '%';
    tpShowResult(result.toLocaleString(undefined, { maximumFractionDigits: 4 }) + suffix, {
      label, raw: result, historyLabel: 'Percentage',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
