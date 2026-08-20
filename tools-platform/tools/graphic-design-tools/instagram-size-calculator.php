<div class="mb-3">
  <label for="f_type">Content Type</label>
  <select id="f_type" class="form-select">
    <option value="square">Instagram Square Post — 1080×1080</option>
    <option value="portrait">Instagram Portrait Post — 1080×1350</option>
    <option value="landscape">Instagram Landscape Post — 1080×566</option>
    <option value="story">Instagram Story / Reel — 1080×1920</option>
    <option value="fb_cover">Facebook Cover Photo — 820×312</option>
    <option value="fb_post">Facebook Post — 1200×630</option>
    <option value="twitter_post">X (Twitter) Post — 1600×900</option>
    <option value="linkedin_cover">LinkedIn Cover — 1584×396</option>
    <option value="youtube_thumb">YouTube Thumbnail — 1280×720</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Get Recommended Size</button>
<script>
const tpSizes = {
  square: [1080, 1080], portrait: [1080, 1350], landscape: [1080, 566], story: [1080, 1920],
  fb_cover: [820, 312], fb_post: [1200, 630], twitter_post: [1600, 900], linkedin_cover: [1584, 396], youtube_thumb: [1280, 720],
};
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const [w, h] = tpSizes[document.getElementById('f_type').value];
  tpShowResult(`${w} × ${h}px`, { label: 'Recommended Dimensions', raw: `${w}x${h}` });
});
</script>
