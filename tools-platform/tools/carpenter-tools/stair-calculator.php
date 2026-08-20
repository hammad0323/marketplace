<div class="mb-3">
  <label for="f_rise">Total Rise (inches — floor to floor height)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rise" placeholder="e.g. 108">
</div>
<div class="mb-3">
  <label for="f_target_riser">Target Riser Height (inches, typically 7-7.75)</label>
  <input type="number" step="0.01" min="1" class="form-control" id="f_target_riser" value="7.5">
</div>
<div class="mb-3">
  <label for="f_tread">Desired Tread Depth (inches, typically 10-11)</label>
  <input type="number" step="0.01" min="1" class="form-control" id="f_tread" value="10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Stair Layout</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const totalRise = tpValidateNumber(document.getElementById('f_rise').value, { label: 'Total rise', min: 0 });
    const targetRiser = tpValidateNumber(document.getElementById('f_target_riser').value, { label: 'Target riser height', min: 1 });
    const tread = tpValidateNumber(document.getElementById('f_tread').value, { label: 'Tread depth', min: 1 });

    const numSteps = Math.round(totalRise / targetRiser);
    const actualRiser = totalRise / numSteps;
    const totalRun = (numSteps - 1) * tread;

    tpShowResult(numSteps + ' steps', {
      label: `Actual riser height: ${actualRiser.toFixed(2)}" — Total run: ${totalRun.toFixed(1)}"`,
      raw: numSteps, historyLabel: 'Stair Steps',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
