<div class="mb-3">
  <label for="f_duration">Duration (seconds)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_duration" placeholder="e.g. 90">
</div>
<div class="mb-3">
  <label for="f_fps">Frame Rate (FPS)</label>
  <select id="f_fps" class="form-select">
    <option value="24">24 fps (Film)</option>
    <option value="25">25 fps (PAL)</option>
    <option value="30" selected>30 fps</option>
    <option value="60">60 fps</option>
    <option value="120">120 fps</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Total Frames</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const duration = tpValidateNumber(document.getElementById('f_duration').value, { label: 'Duration', min: 0 });
    const fps = parseFloat(document.getElementById('f_fps').value);

    const frames = Math.round(duration * fps);

    tpShowResult(frames.toLocaleString() + ' frames', {
      label: `At ${fps} fps for ${duration}s`, raw: frames, historyLabel: 'Frame Count',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
