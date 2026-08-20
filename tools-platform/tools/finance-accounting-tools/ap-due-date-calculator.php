<div class="mb-3">
  <label for="f_invoice_date">Invoice Date</label>
  <input type="date" class="form-control" id="f_invoice_date">
</div>
<div class="mb-3">
  <label for="f_terms">Payment Terms</label>
  <select id="f_terms" class="form-select">
    <option value="0">Due on Receipt</option>
    <option value="10">Net 10</option>
    <option value="15">Net 15</option>
    <option value="30" selected>Net 30</option>
    <option value="45">Net 45</option>
    <option value="60">Net 60</option>
    <option value="90">Net 90</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Due Date</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dateVal = document.getElementById('f_invoice_date').value;
    if (!dateVal) throw new Error('Please select the invoice date.');
    const terms = parseInt(document.getElementById('f_terms').value, 10);

    const invoiceDate = new Date(dateVal + 'T00:00:00');
    const dueDate = new Date(invoiceDate);
    dueDate.setDate(dueDate.getDate() + terms);

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const daysUntilDue = Math.floor((dueDate - today) / 86400000);

    tpShowResult(dueDate.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }), {
      label: daysUntilDue >= 0 ? `${daysUntilDue} day(s) from today` : `${Math.abs(daysUntilDue)} day(s) overdue`,
      raw: dueDate.toISOString().slice(0, 10), historyLabel: 'AP Due Date',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
