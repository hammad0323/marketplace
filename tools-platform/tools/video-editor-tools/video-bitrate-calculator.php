<div class="mb-3">
  <label for="f_target">Target File Size (MB)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_target" placeholder="e.g. 500">
</div>
<div class="mb-3">
  <label for="f_duration">Duration (minutes)</label>
  <input type="number" step="0.1" min="0.01" class="form-control" id="f_duration" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label for="f_audio">Audio Bitrate (kbps)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_audio" value="192">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Required Bitrate</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const targetMB = tpValidateNumber(document.getElementById('f_target').value, { label: 'Target file size', min: 0 });
    const minutes = tpValidateNumber(document.getElementById('f_duration').value, { label: 'Duration', min: 0.01 });
    const audioKbps = tpValidateNumber(document.getElementById('f_audio').value || 0, { label: 'Audio bitrate', min: 0 });

    const seconds = minutes * 60;
    const totalBits = targetMB * 1024 * 1024 * 8;
    const totalBitrateKbps = totalBits / seconds / 1000;
    const videoBitrateKbps = totalBitrateKbps - audioKbps;
    if (videoBitrateKbps <= 0) throw new Error('Target size is too small for this duration and audio bitrate.');

    tpShowResult((videoBitrateKbps / 1000).toFixed(2) + ' Mbps', {
      label: 'Required Video Bitrate', raw: videoBitrateKbps.toFixed(0), historyLabel: 'Video Bitrate',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
