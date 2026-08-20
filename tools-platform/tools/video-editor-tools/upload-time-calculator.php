<div class="mb-3">
  <label for="f_file_size">File Size (GB)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_file_size" placeholder="e.g. 3.5">
</div>
<div class="mb-3">
  <label for="f_speed">Upload Speed</label>
  <div class="input-group">
    <input type="number" step="0.1" min="0.01" class="form-control" id="f_speed" placeholder="e.g. 20">
    <select id="f_speed_unit" class="form-select" style="max-width:140px;">
      <option value="mbps">Mbps</option>
      <option value="MBps">MB/s</option>
    </select>
  </div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Upload Time</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const fileSizeGB = tpValidateNumber(document.getElementById('f_file_size').value, { label: 'File size', min: 0 });
    const speed = tpValidateNumber(document.getElementById('f_speed').value, { label: 'Upload speed', min: 0.01 });
    const unit = document.getElementById('f_speed_unit').value;

    const fileSizeMB = fileSizeGB * 1024;
    const speedMBps = unit === 'mbps' ? speed / 8 : speed;
    const totalSeconds = fileSizeMB / speedMBps;

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.round(totalSeconds % 60);

    tpShowResult(`${hours > 0 ? hours + 'h ' : ''}${minutes}m ${seconds}s`, {
      label: `${fileSizeGB}GB at ${speed}${unit === 'mbps' ? 'Mbps' : 'MB/s'}`, raw: totalSeconds.toFixed(0), historyLabel: 'Upload Time',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
