<div class="mb-3">
  <label for="f_size_per_video">Average Size per Video (GB)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_size_per_video" placeholder="e.g. 2.5">
</div>
<div class="mb-3">
  <label for="f_count">Number of Videos</label>
  <input type="number" step="1" min="0" class="form-control" id="f_count" placeholder="e.g. 200">
</div>
<div class="mb-3">
  <label for="f_available">Available Storage (TB)</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_available" placeholder="e.g. 1" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Storage Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const sizePerVideo = tpValidateNumber(document.getElementById('f_size_per_video').value, { label: 'Size per video', min: 0 });
    const count = tpValidateNumber(document.getElementById('f_count').value, { label: 'Number of videos', min: 0 });
    const availableTB = tpValidateNumber(document.getElementById('f_available').value || 0, { label: 'Available storage', min: 0 });

    const totalGB = sizePerVideo * count;
    const totalTB = totalGB / 1024;
    let note = `≈ ${totalTB.toFixed(2)} TB total`;
    if (availableTB > 0) {
      const remaining = availableTB - totalTB;
      note += remaining >= 0 ? ` — ${remaining.toFixed(2)} TB free after storing` : ` — exceeds available storage by ${Math.abs(remaining).toFixed(2)} TB`;
    }

    tpShowResult(totalGB.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' GB', {
      label: note, raw: totalGB.toFixed(2), historyLabel: 'Video Storage Needed',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
