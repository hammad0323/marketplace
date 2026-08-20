<div class="mb-3">
  <label for="f_container">Container Type</label>
  <select id="f_container" class="form-select">
    <option value="1170">20ft Standard (≈1,170 cu ft, 28,200 kg max)</option>
    <option value="2385">40ft Standard (≈2,385 cu ft, 28,600 kg max)</option>
    <option value="2694">40ft High Cube (≈2,694 cu ft, 28,560 kg max)</option>
  </select>
</div>
<div class="row g-2 mb-3">
  <div class="col-4"><label class="small">Carton L (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_l" placeholder="e.g. 20"></div>
  <div class="col-4"><label class="small">Carton W (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_w" placeholder="e.g. 16"></div>
  <div class="col-4"><label class="small">Carton H (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_h" placeholder="e.g. 14"></div>
</div>
<div class="mb-3">
  <label for="f_carton_weight">Carton Weight (kg)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_carton_weight" placeholder="e.g. 12">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Max Cartons</button>
<script>
const tpContainerMaxKg = { '1170': 28200, '2385': 28600, '2694': 28560 };
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const containerCuFt = parseFloat(document.getElementById('f_container').value);
    const cl = tpValidateNumber(document.getElementById('f_carton_l').value, { label: 'Carton length', min: 0.01 });
    const cw = tpValidateNumber(document.getElementById('f_carton_w').value, { label: 'Carton width', min: 0.01 });
    const ch = tpValidateNumber(document.getElementById('f_carton_h').value, { label: 'Carton height', min: 0.01 });
    const cartonWeight = tpValidateNumber(document.getElementById('f_carton_weight').value, { label: 'Carton weight', min: 0.01 });

    const cartonCuFt = (cl * cw * ch) / 1728;
    const volumeMax = Math.floor((containerCuFt * 0.85) / cartonCuFt); // 85% practical fill factor
    const maxKg = tpContainerMaxKg[document.getElementById('f_container').value];
    const weightMax = Math.floor(maxKg / cartonWeight);
    const limitingFactor = volumeMax <= weightMax ? 'volume' : 'weight';

    tpShowResult(Math.min(volumeMax, weightMax).toLocaleString() + ' cartons', {
      label: `Volume allows ${volumeMax.toLocaleString()}, weight allows ${weightMax.toLocaleString()} — limited by ${limitingFactor}`,
      raw: Math.min(volumeMax, weightMax), historyLabel: 'Container Load',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
