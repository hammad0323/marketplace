<div class="mb-3">
  <label for="f_obtained">Marks Obtained</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_obtained" placeholder="e.g. 78">
</div>
<div class="mb-3">
  <label for="f_total">Total Marks</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_total" placeholder="e.g. 100">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Grade</button>
<script>
function tpLetterGrade(pct) {
  if (pct >= 90) return 'A';
  if (pct >= 80) return 'B';
  if (pct >= 70) return 'C';
  if (pct >= 60) return 'D';
  return 'F';
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const obtained = tpValidateNumber(document.getElementById('f_obtained').value, { label: 'Marks obtained', min: 0 });
    const total = tpValidateNumber(document.getElementById('f_total').value, { label: 'Total marks', min: 0.01 });
    if (obtained > total) throw new Error('Marks obtained cannot exceed total marks.');

    const pct = (obtained / total) * 100;
    const grade = tpLetterGrade(pct);

    tpShowResult(pct.toFixed(1) + '%', { label: 'Percentage — Letter Grade: ' + grade, raw: pct.toFixed(2), historyLabel: 'Grade' });
  } catch (e) { tpShowError(e.message); }
});
</script>
