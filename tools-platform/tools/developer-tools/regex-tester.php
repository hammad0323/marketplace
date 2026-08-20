<div class="mb-3">
  <label for="f_pattern">Regular Expression</label>
  <div class="input-group">
    <span class="input-group-text">/</span>
    <input type="text" class="form-control" id="f_pattern" style="font-family:monospace;" placeholder="^[a-z0-9]+$">
    <span class="input-group-text">/</span>
    <input type="text" class="form-control" id="f_flags" style="max-width:70px;font-family:monospace;" placeholder="gi">
  </div>
</div>
<div class="mb-3">
  <label for="f_text">Test String</label>
  <textarea class="form-control" id="f_text" rows="5" style="font-family:monospace;font-size:.85rem;" placeholder="Text to test against the pattern..."></textarea>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Test Regex</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const pattern = document.getElementById('f_pattern').value;
    const flags = document.getElementById('f_flags').value;
    const text = document.getElementById('f_text').value;
    if (!pattern) throw new Error('Please enter a regular expression pattern.');

    const re = new RegExp(pattern, flags);
    const matches = flags.includes('g') ? [...text.matchAll(re)] : (re.test(text) ? [text.match(re)] : []);

    if (!matches.length) {
      tpShowResult('No matches found', { label: `Pattern: /${pattern}/${flags}`, raw: '' });
    } else {
      const list = matches.map((m) => m[0]).slice(0, 20).join(', ');
      tpShowResult(matches.length + ' match(es)', { label: list, raw: matches.map((m) => m[0]).join('\n') });
    }
  } catch (e) { tpShowError('Invalid regular expression: ' + e.message); }
});
</script>
