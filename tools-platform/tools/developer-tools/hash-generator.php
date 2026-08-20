<div class="mb-3">
  <label for="f_text">Text</label>
  <textarea class="form-control" id="f_text" rows="5" style="font-family:monospace;font-size:.85rem;" placeholder="Type or paste text here..."></textarea>
</div>
<div class="mb-3">
  <label for="f_algo">Algorithm</label>
  <select id="f_algo" class="form-select">
    <option value="SHA-1">SHA-1</option>
    <option value="SHA-256" selected>SHA-256</option>
    <option value="SHA-384">SHA-384</option>
    <option value="SHA-512">SHA-512</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Hash</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', async function () {
  try {
    const text = document.getElementById('f_text').value;
    if (!text) throw new Error('Please enter some text to hash.');
    const algo = document.getElementById('f_algo').value;

    const data = new TextEncoder().encode(text);
    const hashBuffer = await crypto.subtle.digest(algo, data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');

    tpShowResult(hashHex, { label: algo + ' hash', raw: hashHex });
  } catch (e) { tpShowError('Could not generate hash: ' + e.message); }
});
</script>
