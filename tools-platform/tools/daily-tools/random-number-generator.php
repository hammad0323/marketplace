<div class="row g-2 mb-3">
  <div class="col-6"><label class="small">Minimum</label><input type="number" class="form-control" id="f_min" value="1"></div>
  <div class="col-6"><label class="small">Maximum</label><input type="number" class="form-control" id="f_max" value="100"></div>
</div>
<div class="mb-3">
  <label for="f_count">How Many Numbers?</label>
  <input type="number" min="1" max="100" class="form-control" id="f_count" value="1">
</div>
<div class="form-check mb-3">
  <input type="checkbox" class="form-check-input" id="f_unique">
  <label class="form-check-label" for="f_unique">No duplicates</label>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const min = tpValidateNumber(document.getElementById('f_min').value, { label: 'Minimum' });
    const max = tpValidateNumber(document.getElementById('f_max').value, { label: 'Maximum' });
    const count = tpValidateNumber(document.getElementById('f_count').value, { label: 'Count', min: 1, max: 100 });
    const unique = document.getElementById('f_unique').checked;
    if (max <= min) throw new Error('Maximum must be greater than minimum.');
    const range = Math.floor(max) - Math.ceil(min) + 1;
    if (unique && count > range) throw new Error(`Only ${range} unique numbers exist in this range.`);

    const results = [];
    const used = new Set();
    while (results.length < count) {
      const n = Math.floor(Math.random() * range) + Math.ceil(min);
      if (unique) {
        if (used.has(n)) continue;
        used.add(n);
      }
      results.push(n);
    }

    tpShowResult(results.join(', '), { label: `${count} number(s) between ${min} and ${max}`, raw: results.join(', '), historyLabel: 'Random Numbers' });
  } catch (e) { tpShowError(e.message); }
});
</script>
