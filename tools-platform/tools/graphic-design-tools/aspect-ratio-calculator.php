<div class="mb-3">
  <label for="f_width">Original Width (px)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_width" placeholder="e.g. 1920">
</div>
<div class="mb-3">
  <label for="f_height">Original Height (px)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_height" placeholder="e.g. 1080">
</div>
<div class="mb-3">
  <label for="f_new_width">New Width (leave blank if using new height)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_new_width" placeholder="e.g. 800">
</div>
<div class="mb-3">
  <label for="f_new_height">New Height (leave blank if using new width)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_new_height" placeholder="optional">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate</button>
<script>
function tpGcd(a, b) { return b === 0 ? a : tpGcd(b, a % b); }
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Original width', min: 1 });
    const height = tpValidateNumber(document.getElementById('f_height').value, { label: 'Original height', min: 1 });
    const newWidthVal = document.getElementById('f_new_width').value;
    const newHeightVal = document.getElementById('f_new_height').value;

    const divisor = tpGcd(width, height);
    const ratio = `${width / divisor}:${height / divisor}`;

    if (!newWidthVal && !newHeightVal) {
      tpShowResult(ratio, { label: 'Aspect Ratio', raw: ratio, historyLabel: 'Aspect Ratio' });
      return;
    }

    if (newWidthVal) {
      const nw = tpValidateNumber(newWidthVal, { label: 'New width', min: 1 });
      const nh = Math.round((nw * height) / width);
      tpShowResult(`${nw} × ${nh}px`, { label: `Aspect Ratio ${ratio} — Calculated Height`, raw: nh, historyLabel: 'Scaled Dimensions' });
    } else {
      const nh = tpValidateNumber(newHeightVal, { label: 'New height', min: 1 });
      const nw = Math.round((nh * width) / height);
      tpShowResult(`${nw} × ${nh}px`, { label: `Aspect Ratio ${ratio} — Calculated Width`, raw: nw, historyLabel: 'Scaled Dimensions' });
    }
  } catch (e) { tpShowError(e.message); }
});
</script>
