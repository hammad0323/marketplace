<div class="mb-3">
  <label for="f_accrued">Leave Days Accrued</label>
  <input type="number" step="0.5" min="0" class="form-control" id="f_accrued" placeholder="e.g. 20">
</div>
<div class="mb-3">
  <label for="f_used">Leave Days Used</label>
  <input type="number" step="0.5" min="0" class="form-control" id="f_used" placeholder="e.g. 6">
</div>
<div class="mb-3">
  <label for="f_pending">Pending/Requested Leave</label>
  <input type="number" step="0.5" min="0" class="form-control" id="f_pending" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Leave Balance</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const accrued = tpValidateNumber(document.getElementById('f_accrued').value, { label: 'Leave accrued', min: 0 });
    const used = tpValidateNumber(document.getElementById('f_used').value, { label: 'Leave used', min: 0 });
    const pending = tpValidateNumber(document.getElementById('f_pending').value || 0, { label: 'Pending leave', min: 0 });
    if (used > accrued) throw new Error('Leave used cannot exceed leave accrued.');

    const balance = accrued - used - pending;

    tpShowResult(balance.toFixed(1) + ' days', {
      label: `Remaining after ${pending} pending day(s) requested`, raw: balance.toFixed(2), historyLabel: 'Leave Balance',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
