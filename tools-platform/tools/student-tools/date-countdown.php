<div class="mb-3">
  <label for="f_target">Target Date &amp; Time</label>
  <input type="datetime-local" class="form-control" id="f_target">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Countdown</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const val = document.getElementById('f_target').value;
    if (!val) throw new Error('Please pick a target date and time.');
    const target = new Date(val);
    const now = new Date();
    const diffMs = target - now;

    if (diffMs <= 0) {
      tpShowResult('This date has passed', { label: `${Math.abs(Math.floor(diffMs / 86400000))} days ago`, raw: 0 });
      return;
    }

    const days = Math.floor(diffMs / 86400000);
    const hours = Math.floor((diffMs % 86400000) / 3600000);
    const minutes = Math.floor((diffMs % 3600000) / 60000);

    tpShowResult(`${days}d ${hours}h ${minutes}m`, {
      label: `Until ${target.toLocaleString()}`, raw: days, historyLabel: 'Countdown',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
