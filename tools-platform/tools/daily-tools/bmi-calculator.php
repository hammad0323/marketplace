<div class="mb-3">
  <label>Units</label>
  <select id="f_units" class="form-select">
    <option value="metric">Metric (kg / cm)</option>
    <option value="imperial">Imperial (lb / in)</option>
  </select>
</div>
<div class="mb-3">
  <label for="f_weight">Weight</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_weight" placeholder="e.g. 70">
</div>
<div class="mb-3">
  <label for="f_height">Height</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_height" placeholder="e.g. 175">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate BMI</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const units = document.getElementById('f_units').value;
    const weight = tpValidateNumber(document.getElementById('f_weight').value, { label: 'Weight', min: 0 });
    const height = tpValidateNumber(document.getElementById('f_height').value, { label: 'Height', min: 0.01 });

    let bmi;
    if (units === 'metric') {
      const heightM = height / 100;
      bmi = weight / (heightM * heightM);
    } else {
      bmi = (weight / (height * height)) * 703;
    }

    let category = 'Obese';
    if (bmi < 18.5) category = 'Underweight';
    else if (bmi < 25) category = 'Normal weight';
    else if (bmi < 30) category = 'Overweight';

    tpShowResult(bmi.toFixed(1), { label: 'BMI — ' + category, raw: bmi.toFixed(2), historyLabel: 'BMI' });
  } catch (e) { tpShowError(e.message); }
});
</script>
