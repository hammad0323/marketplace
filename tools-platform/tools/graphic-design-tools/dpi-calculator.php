<div class="mb-3">
  <label for="f_pixels">Image Width (pixels)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_pixels" placeholder="e.g. 3000">
</div>
<div class="mb-3">
  <label for="f_print_width">Intended Print Width (inches)</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_print_width" placeholder="e.g. 10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate DPI</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const pixels = tpValidateNumber(document.getElementById('f_pixels').value, { label: 'Image width in pixels', min: 1 });
    const printWidth = tpValidateNumber(document.getElementById('f_print_width').value, { label: 'Print width', min: 0.01 });

    const dpi = pixels / printWidth;
    let quality = 'Low resolution — may look pixelated when printed';
    if (dpi >= 150 && dpi < 300) quality = 'Acceptable for most prints';
    if (dpi >= 300) quality = 'Print-quality resolution';

    tpShowResult(Math.round(dpi) + ' DPI', {
      label: quality, raw: dpi.toFixed(2), historyLabel: 'DPI',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
