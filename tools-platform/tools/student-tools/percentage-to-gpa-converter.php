<div class="mb-3">
  <label for="f_percentage">Percentage Score</label>
  <input type="number" step="0.01" min="0" max="100" class="form-control" id="f_percentage" placeholder="e.g. 87">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert to GPA</button>
<script>
function tpPercentToGpa(pct) {
  if (pct >= 90) return 4.0 - (100 - pct) * 0.02;
  if (pct >= 80) return 3.0 + (pct - 80) * 0.1;
  if (pct >= 70) return 2.0 + (pct - 70) * 0.1;
  if (pct >= 60) return 1.0 + (pct - 60) * 0.1;
  return Math.max(0, (pct / 60) * 1.0);
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const pct = tpValidateNumber(document.getElementById('f_percentage').value, { label: 'Percentage', min: 0, max: 100 });
    const gpa = Math.min(4.0, tpPercentToGpa(pct));

    tpShowResult(gpa.toFixed(2) + ' / 4.0', {
      label: 'Approximate GPA equivalent — scales vary by institution', raw: gpa.toFixed(3), historyLabel: 'GPA Conversion',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
