<div class="text-center mb-3">
  <div id="tpPomodoroDisplay" style="font-size:3rem;font-weight:800;font-variant-numeric:tabular-nums;">25:00</div>
  <div class="text-muted small" id="tpPomodoroPhase">Focus Session</div>
</div>
<div class="row g-2 mb-3">
  <div class="col-6"><label class="form-label small">Focus (minutes)</label><input type="number" min="1" class="form-control" id="f_focus" value="25"></div>
  <div class="col-6"><label class="form-label small">Break (minutes)</label><input type="number" min="1" class="form-control" id="f_break" value="5"></div>
</div>
<div class="d-flex gap-2">
  <button type="button" class="tp-btn-calc" id="tpStartBtn" style="width:auto;flex:1;">Start</button>
  <button type="button" class="btn btn-outline-secondary" id="tpPauseBtn">Pause</button>
  <button type="button" class="btn btn-outline-secondary" id="tpResetBtn2">Reset</button>
</div>
<script>
let tpRemaining = 25 * 60, tpTimer = null, tpOnBreak = false;
function tpRender() {
  const m = Math.floor(tpRemaining / 60).toString().padStart(2, '0');
  const s = (tpRemaining % 60).toString().padStart(2, '0');
  document.getElementById('tpPomodoroDisplay').textContent = `${m}:${s}`;
  document.getElementById('tpPomodoroPhase').textContent = tpOnBreak ? 'Break Time' : 'Focus Session';
}
document.getElementById('tpStartBtn').addEventListener('click', function () {
  if (tpTimer) return;
  tpTimer = setInterval(function () {
    tpRemaining--;
    if (tpRemaining < 0) {
      tpOnBreak = !tpOnBreak;
      const focusMin = parseInt(document.getElementById('f_focus').value, 10) || 25;
      const breakMin = parseInt(document.getElementById('f_break').value, 10) || 5;
      tpRemaining = (tpOnBreak ? breakMin : focusMin) * 60;
      if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: tpOnBreak ? 'Break time!' : 'Back to focus!', showConfirmButton: false, timer: 2000 });
    }
    tpRender();
  }, 1000);
});
document.getElementById('tpPauseBtn').addEventListener('click', function () { clearInterval(tpTimer); tpTimer = null; });
document.getElementById('tpResetBtn2').addEventListener('click', function () {
  clearInterval(tpTimer); tpTimer = null; tpOnBreak = false;
  tpRemaining = (parseInt(document.getElementById('f_focus').value, 10) || 25) * 60;
  tpRender();
});
</script>
