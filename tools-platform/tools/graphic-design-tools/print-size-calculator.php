<div class="row g-2 mb-3">
  <div class="col-6"><label class="small">Image Width (px)</label><input type="number" min="1" class="form-control" id="f_width_px" placeholder="e.g. 3600"></div>
  <div class="col-6"><label class="small">Image Height (px)</label><input type="number" min="1" class="form-control" id="f_height_px" placeholder="e.g. 2400"></div>
</div>
<div class="mb-3">
  <label for="f_dpi">Target DPI</label>
  <select id="f_dpi" class="form-select">
    <option value="72">72 DPI (screen)</option>
    <option value="150">150 DPI (draft print)</option>
    <option value="300" selected>300 DPI (print quality)</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Print Size</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const widthPx = tpValidateNumber(document.getElementById('f_width_px').value, { label: 'Width', min: 1 });
    const heightPx = tpValidateNumber(document.getElementById('f_height_px').value, { label: 'Height', min: 1 });
    const dpi = parseInt(document.getElementById('f_dpi').value, 10);

    const widthIn = widthPx / dpi;
    const heightIn = heightPx / dpi;
    const widthCm = widthIn * 2.54;
    const heightCm = heightIn * 2.54;

    tpShowResult(`${widthIn.toFixed(2)}" × ${heightIn.toFixed(2)}"`, {
      label: `${widthCm.toFixed(1)}cm × ${heightCm.toFixed(1)}cm at ${dpi} DPI`, raw: `${widthIn.toFixed(2)}x${heightIn.toFixed(2)}in`, historyLabel: 'Print Size',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
