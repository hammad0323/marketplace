<div class="mb-3">
  <label for="f_file">Choose Image</label>
  <input type="file" class="form-control" id="f_file" accept="image/png,image/jpeg,image/webp">
</div>
<div class="row g-2 mb-3">
  <div class="col-6"><label class="small">Width (px)</label><input type="number" min="1" class="form-control" id="f_width" placeholder="e.g. 800"></div>
  <div class="col-6"><label class="small">Height (px)</label><input type="number" min="1" class="form-control" id="f_height" placeholder="e.g. 600"></div>
</div>
<div class="form-check mb-3">
  <input type="checkbox" class="form-check-input" id="f_lock_ratio" checked>
  <label class="form-check-label" for="f_lock_ratio">Lock aspect ratio</label>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Resize Image</button>
<div id="tpResizePreview" class="mt-3"></div>
<script>
let tpOriginalRatio = null;
document.getElementById('f_file').addEventListener('change', function () {
  if (!this.files[0]) return;
  const img = new Image();
  img.onload = function () { tpOriginalRatio = img.width / img.height; };
  img.src = URL.createObjectURL(this.files[0]);
});
document.getElementById('f_width').addEventListener('input', function () {
  if (document.getElementById('f_lock_ratio').checked && tpOriginalRatio) {
    document.getElementById('f_height').value = Math.round(this.value / tpOriginalRatio);
  }
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const fileInput = document.getElementById('f_file');
  if (!fileInput.files || !fileInput.files[0]) { tpShowError('Please choose an image file first.'); return; }
  try {
    const width = tpValidateNumber(document.getElementById('f_width').value, { label: 'Width', min: 1 });
    const height = tpValidateNumber(document.getElementById('f_height').value, { label: 'Height', min: 1 });
    const file = fileInput.files[0];

    const img = new Image();
    const reader = new FileReader();
    reader.onload = function (e) {
      img.onload = function () {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(img, 0, 0, width, height);
        const mime = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        canvas.toBlob(function (blob) {
          const url = URL.createObjectURL(blob);
          document.getElementById('tpResizePreview').innerHTML =
            `<img src="${url}" style="max-width:100%;max-height:200px;border-radius:8px;" class="mb-2"><br>` +
            `<a href="${url}" download="resized.jpg" class="btn btn-outline-secondary btn-sm">Download Resized Image</a>`;
          tpShowResult(`${width} × ${height}px`, { label: (blob.size / 1024).toFixed(1) + ' KB', raw: `${width}x${height}` });
        }, mime, 0.92);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  } catch (e) { tpShowError(e.message); }
});
</script>
