<div class="mb-3">
  <label for="f_json">JSON Input</label>
  <textarea class="form-control" id="f_json" rows="8" style="font-family:monospace;font-size:.85rem;" placeholder='{"example": true, "values": [1,2,3]}'></textarea>
</div>
<div class="d-flex gap-2 mb-2">
  <button type="button" class="tp-btn-calc" id="tpCalculateBtn" style="width:auto;flex:1;">Format &amp; Validate</button>
  <button type="button" class="btn btn-outline-secondary" id="tpMinifyBtn">Minify</button>
</div>
<script>
function tpGetJson() {
  const raw = document.getElementById('f_json').value.trim();
  if (!raw) throw new Error('Please paste some JSON to format.');
  try {
    return JSON.parse(raw);
  } catch (e) {
    throw new Error('Invalid JSON: ' + e.message);
  }
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const parsed = tpGetJson();
    const formatted = JSON.stringify(parsed, null, 2);
    document.getElementById('f_json').value = formatted;
    tpShowResult('Valid JSON ✓ (' + formatted.length + ' characters)', { label: 'Validation Result', raw: formatted });
  } catch (e) { tpShowError(e.message); }
});
document.getElementById('tpMinifyBtn').addEventListener('click', function () {
  try {
    const parsed = tpGetJson();
    const minified = JSON.stringify(parsed);
    document.getElementById('f_json').value = minified;
    tpShowResult('Minified (' + minified.length + ' characters)', { label: 'Result', raw: minified });
  } catch (e) { tpShowError(e.message); }
});
</script>
