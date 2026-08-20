<div class="mb-3">
  <label for="f_bitrate">Bitrate (Mbps)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_bitrate" placeholder="e.g. 25">
</div>
<div class="mb-3">
  <label for="f_duration">Duration (minutes)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_duration" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label for="f_audio">Audio Bitrate (kbps, optional)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_audio" value="192">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate File Size</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const bitrateMbps = tpValidateNumber(document.getElementById('f_bitrate').value, { label: 'Video bitrate', min: 0 });
    const minutes = tpValidateNumber(document.getElementById('f_duration').value, { label: 'Duration', min: 0 });
    const audioKbps = tpValidateNumber(document.getElementById('f_audio').value || 0, { label: 'Audio bitrate', min: 0 });

    const seconds = minutes * 60;
    const totalBitsPerSec = (bitrateMbps * 1_000_000) + (audioKbps * 1000);
    const totalBytes = (totalBitsPerSec * seconds) / 8;
    const totalMB = totalBytes / (1024 * 1024);
    const totalGB = totalMB / 1024;

    tpShowResult(totalGB >= 1 ? totalGB.toFixed(2) + ' GB' : totalMB.toFixed(1) + ' MB', {
      label: 'Estimated File Size', raw: totalMB.toFixed(2), historyLabel: 'Video File Size',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
