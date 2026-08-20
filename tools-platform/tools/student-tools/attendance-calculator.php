<div class="mb-3">
  <label for="f_attended">Classes Attended</label>
  <input type="number" step="1" min="0" class="form-control" id="f_attended" placeholder="e.g. 42">
</div>
<div class="mb-3">
  <label for="f_total">Total Classes Held</label>
  <input type="number" step="1" min="1" class="form-control" id="f_total" placeholder="e.g. 50">
</div>
<div class="mb-3">
  <label for="f_target">Required Attendance (%)</label>
  <input type="number" step="0.1" min="0" max="100" class="form-control" id="f_target" value="75">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Attendance</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const attended = tpValidateNumber(document.getElementById('f_attended').value, { label: 'Classes attended', min: 0 });
    const total = tpValidateNumber(document.getElementById('f_total').value, { label: 'Total classes', min: 1 });
    const target = tpValidateNumber(document.getElementById('f_target').value, { label: 'Required attendance', min: 0, max: 100 });
    if (attended > total) throw new Error('Classes attended cannot exceed total classes held.');

    const pct = (attended / total) * 100;
    let note;
    if (pct >= target) {
      const maxSkippable = Math.floor((attended - (target / 100) * total) / (target / 100));
      note = `You can afford to miss ${Math.max(0, maxSkippable)} more class(es) and stay at or above ${target}%.`;
    } else {
      const needed = Math.ceil(((target / 100) * total - attended) / (1 - target / 100));
      note = `You need to attend ${needed} more class(es) in a row to reach ${target}%.`;
    }

    tpShowResult(pct.toFixed(1) + '%', { label: 'Current Attendance — ' + note, raw: pct.toFixed(2), historyLabel: 'Attendance' });
  } catch (e) { tpShowError(e.message); }
});
</script>
