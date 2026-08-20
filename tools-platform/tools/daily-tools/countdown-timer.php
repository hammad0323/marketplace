<div class="mb-3">
  <label for="f_target">Count Down To</label>
  <input type="datetime-local" class="form-control" id="f_target">
</div>
<button type="button" class="tp-btn-calc" id="tpStartBtn">Start Countdown</button>
<div class="text-center mt-3">
  <div id="tpCountdownDisplay" style="font-size:2.2rem;font-weight:800;font-variant-numeric:tabular-nums;">—</div>
</div>
<script>
let tpCountdownInterval = null;
function tpFormatRemaining(ms) {
  if (ms <= 0) return "Time's up!";
  const days = Math.floor(ms / 86400000);
  const hours = Math.floor((ms % 86400000) / 3600000);
  const minutes = Math.floor((ms % 3600000) / 60000);
  const seconds = Math.floor((ms % 60000) / 1000);
  return `${days}d ${hours}h ${minutes}m ${seconds}s`;
}
document.getElementById('tpStartBtn').addEventListener('click', function () {
  const val = document.getElementById('f_target').value;
  if (!val) { tpShowError('Please pick a target date and time.'); return; }
  const target = new Date(val).getTime();

  clearInterval(tpCountdownInterval);
  function tick() {
    const remaining = target - Date.now();
    document.getElementById('tpCountdownDisplay').textContent = tpFormatRemaining(remaining);
    if (remaining <= 0) clearInterval(tpCountdownInterval);
  }
  tick();
  tpCountdownInterval = setInterval(tick, 1000);
  tpShowResult('Countdown started ✓', { label: `Counting down to ${new Date(val).toLocaleString()}`, raw: val });
});
</script>
