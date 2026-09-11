<div class="mb-3"><label for="f_name">Your Name</label><input type="text" class="form-control" id="f_name" placeholder="e.g. Bilal"></div>
<div class="mb-3"><label for="f_company">Your Company</label><input type="text" class="form-control" id="f_company" placeholder="e.g. Khan Traders"></div>
<div class="mb-3"><label for="f_product">Product / Service</label><input type="text" class="form-control" id="f_product" placeholder="e.g. office supplies"></div>
<div class="mb-3"><label for="f_benefit">Main Benefit to the Customer</label><input type="text" class="form-control" id="f_benefit" placeholder="e.g. save 20% on your monthly stationery costs"></div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Cold Call Script</button>
<div class="mt-3" id="tpScriptOutputWrap" style="display:none;">
  <label>Generated Script</label>
  <textarea class="form-control" id="tpScriptOutput" rows="9" readonly style="font-size:.9rem;"></textarea>
</div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const name = document.getElementById('f_name').value.trim() || '[Your Name]';
    const company = document.getElementById('f_company').value.trim() || '[Your Company]';
    const product = document.getElementById('f_product').value.trim() || '[product/service]';
    const benefit = document.getElementById('f_benefit').value.trim() || '[main benefit]';

    const script = `Hi, this is ${name} calling from ${company}. Am I catching you at an okay time?

[If yes:] Great — the reason I'm calling is we help businesses like yours with ${product}, and our customers typically ${benefit}. I wanted to see if that's something worth a quick conversation for you?

[If they're interested:] Perfect — would you have 10 minutes this week for a quick call, or should I send over some details on WhatsApp first?

[If they're busy/not interested:] No problem at all — would it be okay if I sent a short message with the details, so you have it for later?`;

    document.getElementById('tpScriptOutput').value = script;
    document.getElementById('tpScriptOutputWrap').style.display = 'block';
    tpShowResult('Script generated ✓', { label: 'Your cold call script — see it below, or click Copy', raw: script, historyLabel: 'Cold Call Script' });
  } catch (e) { tpShowError(e.message); }
});
</script>
