<div class="mb-3">
  <label for="f_objection">Common Objection</label>
  <select class="form-select" id="f_objection">
    <option value="price">"It's too expensive"</option>
    <option value="think">"I need to think about it"</option>
    <option value="competitor">"We already use a competitor"</option>
    <option value="notnow">"Not the right time"</option>
    <option value="notneeded">"We don't need this"</option>
    <option value="notauth">"I need to check with someone else"</option>
  </select>
</div>
<div class="mb-3"><label for="f_benefit">Your Product's Key Advantage</label><input type="text" class="form-control" id="f_benefit" placeholder="e.g. faster delivery, better support, lower cost per unit"></div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Response</button>
<div class="mt-3" id="tpScriptOutputWrap" style="display:none;">
  <label>Suggested Response</label>
  <textarea class="form-control" id="tpScriptOutput" rows="5" readonly style="font-size:.9rem;"></textarea>
</div>
<script>
const tpObjectionResponses = {
  price: (b) => `I completely understand — price matters. Can I ask, is it the price itself, or how it compares to the value you'd get? Because when you factor in ${b || 'the benefits we offer'}, most customers find it pays for itself quickly. Would it help if I broke down exactly what's included?`,
  think: (b) => `Of course, it's a decision worth thinking through. Just so I can help — is there a specific concern I can address right now? A lot of the time it comes down to ${b || 'one or two details'}, and I'd rather clear that up today than leave you wondering.`,
  competitor: (b) => `That's great, it means you already see the value in this type of solution. What we hear most often from people who switch is ${b || 'our key advantage'} — would it be worth a quick comparison so you can see for yourself?`,
  notnow: (b) => `Totally understand, timing matters. Can I ask what would make it the "right time"? In the meantime, since ${b || 'this benefit'} keeps adding up the longer you wait, would it help to at least lock in today's terms for when you're ready?`,
  notneeded: (b) => `Fair enough — can I ask what you're currently doing instead? A lot of customers didn't think they needed this until they saw ${b || 'the specific benefit'} — happy to show you a quick example if you're open to it.`,
  notauth: (b) => `No problem at all — who else would be involved in this decision? I'm happy to put together a short summary highlighting ${b || 'the key benefit'} that you can share with them directly.`,
};
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const objection = document.getElementById('f_objection').value;
    const benefit = document.getElementById('f_benefit').value.trim();
    const response = tpObjectionResponses[objection](benefit);

    document.getElementById('tpScriptOutput').value = response;
    document.getElementById('tpScriptOutputWrap').style.display = 'block';
    tpShowResult('Response generated ✓', { label: 'Suggested response — see it below, or click Copy', raw: response, historyLabel: 'Objection Handling' });
  } catch (e) { tpShowError(e.message); }
});
</script>
