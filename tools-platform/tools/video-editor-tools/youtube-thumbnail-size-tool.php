<div class="mb-3">
  <label for="f_type">Asset Type</label>
  <select id="f_type" class="form-select">
    <option value="thumbnail">Video Thumbnail — 1280×720</option>
    <option value="channel_banner">Channel Banner — 2560×1440 (safe area 1546×423)</option>
    <option value="channel_icon">Channel Icon — 800×800</option>
    <option value="shorts">Shorts / Vertical Video — 1080×1920</option>
    <option value="end_card">End Screen Element — 1280×720</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Get Recommended Size</button>
<script>
const tpSizes = {
  thumbnail: '1280 × 720px', channel_banner: '2560 × 1440px (safe area: 1546 × 423px)',
  channel_icon: '800 × 800px', shorts: '1080 × 1920px', end_card: '1280 × 720px',
};
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const size = tpSizes[document.getElementById('f_type').value];
  tpShowResult(size, { label: 'Recommended Dimensions', raw: size });
});
</script>
