<div class="mb-3">
  <label for="f_start">Start Time</label>
  <input type="time" class="form-control" id="f_start">
</div>
<div class="mb-3">
  <label for="f_end">End Time</label>
  <input type="time" class="form-control" id="f_end">
</div>
<div class="mb-3">
  <label for="f_break">Unpaid Break (minutes)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_break" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Hours Worked</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const start = document.getElementById('f_start').value;
    const end = document.getElementById('f_end').value;
    const breakMin = tpValidateNumber(document.getElementById('f_break').value || 0, { label: 'Break minutes', min: 0 });
    if (!start || !end) throw new Error('Please provide both a start time and an end time.');

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let minutes = (eh * 60 + em) - (sh * 60 + sm);
    if (minutes < 0) minutes += 24 * 60; // overnight shift

    const workedMinutes = minutes - breakMin;
    if (workedMinutes < 0) throw new Error('Break time cannot exceed the total shift length.');

    const hours = Math.floor(workedMinutes / 60);
    const mins = Math.round(workedMinutes % 60);

    tpShowResult(`${hours}h ${mins}m`, {
      label: 'Total Hours Worked', raw: (workedMinutes / 60).toFixed(2), historyLabel: 'Hours Worked',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
