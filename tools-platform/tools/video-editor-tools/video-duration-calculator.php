<div class="mb-3">
  <label for="f_frames">Total Frame Count</label>
  <input type="number" step="1" min="0" class="form-control" id="f_frames" placeholder="e.g. 2700">
</div>
<div class="mb-3">
  <label for="f_fps">Frame Rate (FPS)</label>
  <select id="f_fps" class="form-select">
    <option value="24">24 fps</option>
    <option value="25">25 fps</option>
    <option value="30" selected>30 fps</option>
    <option value="60">60 fps</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Duration</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const frames = tpValidateNumber(document.getElementById('f_frames').value, { label: 'Frame count', min: 0 });
    const fps = parseFloat(document.getElementById('f_fps').value);

    const totalSeconds = frames / fps;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = (totalSeconds % 60).toFixed(2);

    tpShowResult(`${minutes}m ${seconds}s`, {
      label: `${totalSeconds.toFixed(2)} total seconds at ${fps} fps`, raw: totalSeconds.toFixed(2), historyLabel: 'Video Duration',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
