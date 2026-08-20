<div class="mb-3">
  <label for="f_bar_size">Rebar Size</label>
  <select id="f_bar_size" class="form-select">
    <option value="0.376">#3 (3/8")</option>
    <option value="0.668">#4 (1/2")</option>
    <option value="1.043">#5 (5/8")</option>
    <option value="1.502">#6 (3/4")</option>
    <option value="2.044">#7 (7/8")</option>
    <option value="2.67">#8 (1")</option>
  </select>
</div>
<div class="mb-3">
  <label for="f_length">Total Length (ft)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_length" placeholder="e.g. 500">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Rebar Weight</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const weightPerFt = parseFloat(document.getElementById('f_bar_size').value);
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Total length', min: 0 });

    const totalWeight = weightPerFt * length;

    tpShowResult(totalWeight.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' lbs', {
      label: `${weightPerFt} lbs/ft × ${length} ft`, raw: totalWeight.toFixed(2), historyLabel: 'Rebar Weight',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
