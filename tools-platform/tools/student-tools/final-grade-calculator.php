<p class="text-muted small">Enter your score and weight for each graded component (weights should total 100%).</p>
<div id="tpGradeRows">
  <div class="row g-2 mb-2 grade-row">
    <div class="col-6"><input type="text" class="form-control grade-name" placeholder="Component (e.g. Midterm)"></div>
    <div class="col-3"><input type="number" class="form-control grade-score" placeholder="Score %" min="0" max="100"></div>
    <div class="col-3"><input type="number" class="form-control grade-weight" placeholder="Weight %" min="0" max="100"></div>
  </div>
</div>
<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="tpAddRow">+ Add Component</button>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Final Grade</button>
<script>
document.getElementById('tpAddRow').addEventListener('click', function () {
  const container = document.getElementById('tpGradeRows');
  container.insertAdjacentHTML('beforeend', container.firstElementChild.outerHTML);
  container.lastElementChild.querySelectorAll('input').forEach((el) => el.value = '');
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const scores = document.querySelectorAll('.grade-score');
    const weights = document.querySelectorAll('.grade-weight');
    let weightedSum = 0, totalWeight = 0;
    for (let i = 0; i < scores.length; i++) {
      if (!scores[i].value || !weights[i].value) continue;
      const score = tpValidateNumber(scores[i].value, { label: 'Score', min: 0, max: 100 });
      const weight = tpValidateNumber(weights[i].value, { label: 'Weight', min: 0, max: 100 });
      weightedSum += score * (weight / 100);
      totalWeight += weight;
    }
    if (totalWeight === 0) throw new Error('Enter at least one component with a score and weight.');

    const finalGrade = weightedSum * (100 / totalWeight);
    tpShowResult(finalGrade.toFixed(2) + '%', {
      label: totalWeight !== 100 ? `Weights totaled ${totalWeight}% — normalized to 100%` : 'Final weighted grade',
      raw: finalGrade.toFixed(2), historyLabel: 'Final Grade',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
