<div class="mb-3">
  <label for="f_distance">Total Distance (miles)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_distance" placeholder="e.g. 250">
</div>
<div class="mb-3">
  <label for="f_speed">Average Speed (mph)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_speed" value="55">
</div>
<div class="mb-3">
  <label for="f_stops">Number of Stops</label>
  <input type="number" step="1" min="0" class="form-control" id="f_stops" value="0">
</div>
<div class="mb-3">
  <label for="f_stop_time">Time per Stop (minutes)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_stop_time" value="15">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate ETA</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const distance = tpValidateNumber(document.getElementById('f_distance').value, { label: 'Distance', min: 0 });
    const speed = tpValidateNumber(document.getElementById('f_speed').value, { label: 'Average speed', min: 1 });
    const stops = tpValidateNumber(document.getElementById('f_stops').value || 0, { label: 'Number of stops', min: 0 });
    const stopTime = tpValidateNumber(document.getElementById('f_stop_time').value || 0, { label: 'Time per stop', min: 0 });

    const drivingHours = distance / speed;
    const stopHours = (stops * stopTime) / 60;
    const totalHours = drivingHours + stopHours;

    const eta = new Date(Date.now() + totalHours * 3600000);
    const hours = Math.floor(totalHours);
    const minutes = Math.round((totalHours - hours) * 60);

    tpShowResult(`${hours}h ${minutes}m`, {
      label: `Estimated arrival: ${eta.toLocaleString()}`, raw: totalHours.toFixed(2), historyLabel: 'Delivery ETA',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
