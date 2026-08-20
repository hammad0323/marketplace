<div class="mb-3">
  <label for="f_count">How Many?</label>
  <input type="number" min="1" max="50" class="form-control" id="f_count" value="3">
</div>
<div class="mb-3">
  <label for="f_unit">Unit</label>
  <select id="f_unit" class="form-select">
    <option value="paragraphs" selected>Paragraphs</option>
    <option value="sentences">Sentences</option>
    <option value="words">Words</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Lorem Ipsum</button>
<textarea id="tpLoremOutput" class="form-control mt-3" rows="8" readonly style="font-size:.9rem;"></textarea>
<script>
const tpLoremWords = ('lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua enim ad minim veniam quis nostrud exercitation ullamco laboris nisi aliquip ex ea commodo consequat duis aute irure in reprehenderit voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt culpa qui officia deserunt mollit anim id est laborum').split(' ');

function tpRandomWord() { return tpLoremWords[Math.floor(Math.random() * tpLoremWords.length)]; }
function tpSentence() {
  const len = 6 + Math.floor(Math.random() * 10);
  const words = Array.from({ length: len }, tpRandomWord);
  const s = words.join(' ');
  return s.charAt(0).toUpperCase() + s.slice(1) + '.';
}
function tpParagraph() {
  const len = 3 + Math.floor(Math.random() * 4);
  return Array.from({ length: len }, tpSentence).join(' ');
}

document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const count = tpValidateNumber(document.getElementById('f_count').value, { label: 'Count', min: 1, max: 50 });
    const unit = document.getElementById('f_unit').value;
    let output;
    if (unit === 'words') output = Array.from({ length: count }, tpRandomWord).join(' ');
    else if (unit === 'sentences') output = Array.from({ length: count }, tpSentence).join(' ');
    else output = Array.from({ length: count }, tpParagraph).join('\n\n');

    document.getElementById('tpLoremOutput').value = output;
    tpShowResult(`${count} ${unit} generated ✓`, { label: 'Result', raw: output });
  } catch (e) { tpShowError(e.message); }
});
</script>
