<p class="text-muted small">Enter each course's credit hours and letter grade.</p>
<div id="tpGpaRows">
  <div class="row g-2 mb-2 tp-gpa-row">
    <div class="col-7"><input type="number" class="form-control gpa-credits" placeholder="Credit hours" min="0" step="0.5"></div>
    <div class="col-5">
      <select class="form-select gpa-grade">
        <option value="4">A (4.0)</option><option value="3.7">A- (3.7)</option>
        <option value="3.3">B+ (3.3)</option><option value="3">B (3.0)</option><option value="2.7">B- (2.7)</option>
        <option value="2.3">C+ (2.3)</option><option value="2">C (2.0)</option><option value="1.7">C- (1.7)</option>
        <option value="1">D (1.0)</option><option value="0">F (0.0)</option>
      </select>
    </div>
  </div>
</div>
<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="tpAddRow">+ Add Course</button>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate GPA</button>
<script>
document.getElementById('tpAddRow').addEventListener('click', function () {
  const container = document.getElementById('tpGpaRows');
  container.insertAdjacentHTML('beforeend', container.firstElementChild.outerHTML);
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const credits = document.querySelectorAll('.gpa-credits');
    const grades = document.querySelectorAll('.gpa-grade');
    let totalPoints = 0, totalCredits = 0;
    for (let i = 0; i < credits.length; i++) {
      const c = tpValidateNumber(credits[i].value, { label: 'Credit hours', min: 0 });
      if (c === 0) continue;
      totalPoints += c * parseFloat(grades[i].value);
      totalCredits += c;
    }
    if (totalCredits === 0) throw new Error('Enter credit hours for at least one course.');

    const gpa = totalPoints / totalCredits;
    tpShowResult(gpa.toFixed(2), { label: `GPA across ${totalCredits} credit hours`, raw: gpa.toFixed(3), historyLabel: 'GPA' });
  } catch (e) { tpShowError(e.message); }
});
</script>
