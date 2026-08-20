<div class="mb-3">
  <label for="f_dob">Date of Birth</label>
  <input type="date" class="form-control" id="f_dob">
</div>
<div class="mb-3">
  <label for="f_asof">As Of Date (defaults to today)</label>
  <input type="date" class="form-control" id="f_asof">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Age</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dobVal = document.getElementById('f_dob').value;
    if (!dobVal) throw new Error('Please enter your date of birth.');
    const dob = new Date(dobVal + 'T00:00:00');
    const asOfVal = document.getElementById('f_asof').value;
    const asOf = asOfVal ? new Date(asOfVal + 'T00:00:00') : new Date();
    if (dob > asOf) throw new Error('Date of birth cannot be after the "as of" date.');

    let years = asOf.getFullYear() - dob.getFullYear();
    let months = asOf.getMonth() - dob.getMonth();
    let days = asOf.getDate() - dob.getDate();
    if (days < 0) {
      months--;
      days += new Date(asOf.getFullYear(), asOf.getMonth(), 0).getDate();
    }
    if (months < 0) { years--; months += 12; }

    tpShowResult(`${years}y ${months}m ${days}d`, {
      label: 'Current Age', raw: years, historyLabel: 'Age',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
