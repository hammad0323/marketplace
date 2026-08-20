<div class="mb-3">
  <label for="f_count">How many UUIDs?</label>
  <input type="number" min="1" max="100" class="form-control" id="f_count" value="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate UUID (v4)</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const count = tpValidateNumber(document.getElementById('f_count').value, { label: 'Count', min: 1, max: 100 });
    const uuids = [];
    for (let i = 0; i < count; i++) {
      uuids.push(crypto.randomUUID ? crypto.randomUUID() : tpFallbackUuid());
    }
    const joined = uuids.join('\n');
    tpShowResult(uuids[0] + (uuids.length > 1 ? ` (+${uuids.length - 1} more — copy to see all)` : ''), { label: 'Generated UUID(s)', raw: joined });
  } catch (e) { tpShowError(e.message); }
});
function tpFallbackUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
</script>
