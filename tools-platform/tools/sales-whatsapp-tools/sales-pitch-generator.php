<div class="mb-3"><label for="f_product">Product / Service Name</label><input type="text" class="form-control" id="f_product" placeholder="e.g. our accounting software"></div>
<div class="mb-3"><label for="f_audience">Target Customer</label><input type="text" class="form-control" id="f_audience" placeholder="e.g. small business owners"></div>
<div class="mb-3"><label for="f_problem">Problem It Solves</label><input type="text" class="form-control" id="f_problem" placeholder="e.g. losing track of unpaid invoices"></div>
<div class="mb-3"><label for="f_benefit">Key Benefit</label><input type="text" class="form-control" id="f_benefit" placeholder="e.g. get paid 30% faster"></div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Sales Pitch</button>
<div class="mt-3" id="tpScriptOutputWrap" style="display:none;">
  <label>Generated Pitch</label>
  <textarea class="form-control" id="tpScriptOutput" rows="7" readonly style="font-size:.9rem;"></textarea>
</div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const product = document.getElementById('f_product').value.trim() || '[product/service]';
    const audience = document.getElementById('f_audience').value.trim() || '[target customer]';
    const problem = document.getElementById('f_problem').value.trim() || '[the problem]';
    const benefit = document.getElementById('f_benefit').value.trim() || '[key benefit]';

    const pitch = `If you're a ${audience} and you're tired of ${problem}, ${product} was built for exactly that.

Instead of dealing with ${problem} every week, you'll be able to ${benefit} — without adding extra work to your day.

That's why businesses like yours choose us: it's simple to start, it fits into how you already work, and the results show up fast.

Want to see how it would work for you specifically? I'd love to walk you through it.`;

    document.getElementById('tpScriptOutput').value = pitch;
    document.getElementById('tpScriptOutputWrap').style.display = 'block';
    tpShowResult('Pitch generated ✓', { label: 'Your sales pitch — see it below, or click Copy', raw: pitch, historyLabel: 'Sales Pitch' });
  } catch (e) { tpShowError(e.message); }
});
</script>
