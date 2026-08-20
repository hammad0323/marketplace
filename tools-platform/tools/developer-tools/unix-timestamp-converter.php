<div class="mb-3">
  <label for="f_timestamp">Unix Timestamp (seconds)</label>
  <input type="number" class="form-control" id="f_timestamp" placeholder="e.g. 1735689600">
</div>
<button type="button" class="btn btn-outline-secondary mb-3" id="tpNowBtn">Use Current Timestamp</button>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert to Date</button>
<hr>
<div class="mb-3">
  <label for="f_datetime">Or convert a date to a timestamp</label>
  <input type="datetime-local" class="form-control" id="f_datetime">
</div>
<button type="button" class="btn btn-outline-secondary" id="tpReverseBtn">Convert to Timestamp</button>
<script>
document.getElementById('tpNowBtn').addEventListener('click', function () {
  document.getElementById('f_timestamp').value = Math.floor(Date.now() / 1000);
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const ts = tpValidateNumber(document.getElementById('f_timestamp').value, { label: 'Timestamp' });
    const date = new Date(ts * 1000);
    if (isNaN(date.getTime())) throw new Error('That timestamp could not be converted to a valid date.');
    tpShowResult(date.toUTCString(), { label: 'Converted Date (UTC)', raw: date.toISOString() });
  } catch (e) { tpShowError(e.message); }
});
document.getElementById('tpReverseBtn').addEventListener('click', function () {
  try {
    const val = document.getElementById('f_datetime').value;
    if (!val) throw new Error('Please pick a date and time.');
    const ts = Math.floor(new Date(val).getTime() / 1000);
    tpShowResult(ts.toString(), { label: 'Unix Timestamp', raw: ts });
  } catch (e) { tpShowError(e.message); }
});
</script>
