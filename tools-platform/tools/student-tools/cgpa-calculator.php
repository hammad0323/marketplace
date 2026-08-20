<p class="text-muted small">Enter each completed semester's GPA and credit hours.</p>
<div id="tpCgpaRows">
  <div class="row g-2 mb-2 cgpa-row">
    <div class="col-6"><input type="number" class="form-control cgpa-gpa" placeholder="Semester GPA" min="0" max="4" step="0.01"></div>
    <div class="col-6"><input type="number" class="form-control cgpa-credits" placeholder="Credit hours" min="0" step="0.5"></div>
  </div>
</div>
<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="tpAddRow">+ Add Semester</button>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate CGPA</button>
<script>
document.getElementById('tpAddRow').addEventListener('click', function () {
  const container = document.getElementById('tpCgpaRows');
  container.insertAdjacentHTML('beforeend', container.firstElementChild.outerHTML);
  container.lastElementChild.querySelectorAll('input').forEach((el) => el.value = '');
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const gpas = document.querySelectorAll('.cgpa-gpa');
    const credits = document.querySelectorAll('.cgpa-credits');
    let totalPoints = 0, totalCredits = 0;
    for (let i = 0; i < gpas.length; i++) {
      if (!gpas[i].value || !credits[i].value) continue;
      const gpa = tpValidateNumber(gpas[i].value, { label: 'Semester GPA', min: 0, max: 4 });
      const c = tpValidateNumber(credits[i].value, { label: 'Credit hours', min: 0 });
      totalPoints += gpa * c;
      totalCredits += c;
    }
    if (totalCredits === 0) throw new Error('Enter at least one semester with GPA and credit hours.');

    const cgpa = totalPoints / totalCredits;
    tpShowResult(cgpa.toFixed(3), { label: `Cumulative across ${totalCredits} credit hours`, raw: cgpa.toFixed(4), historyLabel: 'CGPA' });
  } catch (e) { tpShowError(e.message); }
});
</script>
