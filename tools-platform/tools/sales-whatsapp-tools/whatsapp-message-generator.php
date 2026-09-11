<div class="mb-3">
  <label for="f_type">Message Type</label>
  <select class="form-select" id="f_type">
    <option value="first_contact">First Contact</option>
    <option value="follow_up">Follow-up</option>
    <option value="quotation">Price Quotation Sent</option>
    <option value="payment_reminder">Payment Reminder</option>
    <option value="order_confirmation">Order Confirmation</option>
    <option value="thank_you">Thank You</option>
  </select>
</div>
<div class="mb-3"><label for="f_name">Customer Name</label><input type="text" class="form-control" id="f_name" placeholder="e.g. Ahmed"></div>
<div class="mb-3"><label for="f_business">Your Business Name</label><input type="text" class="form-control" id="f_business" placeholder="e.g. Khan Traders"></div>
<div class="mb-3"><label for="f_extra">Detail <span class="text-muted small">(product / amount / order #, depending on type)</span></label><input type="text" class="form-control" id="f_extra" placeholder="e.g. Rs 5,000 or Order #1234"></div>
<div class="mb-3">
  <label for="f_phone">Customer Phone (optional — adds a WhatsApp link)</label>
  <input type="text" class="form-control" id="f_phone" placeholder="03001234567">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Message</button>
<script>
const tpTemplates = {
  first_contact: (n, b, x) => `Hi ${n || 'there'}! This is ${b || 'our team'}. Thank you for your interest — how can we help you today?`,
  follow_up: (n, b, x) => `Hi ${n || 'there'}, just following up from ${b || 'us'} — did you have any questions about ${x || 'our offer'}? Happy to help!`,
  quotation: (n, b, x) => `Hi ${n || 'there'}, here's your quotation from ${b || 'us'}: ${x || '[amount]'}. Let us know if you'd like to proceed!`,
  payment_reminder: (n, b, x) => `Hi ${n || 'there'}, a friendly reminder from ${b || 'us'} that a payment of ${x || '[amount]'} is due. Please let us know once it's arranged. Thank you!`,
  order_confirmation: (n, b, x) => `Hi ${n || 'there'}, your order ${x || ''} has been confirmed by ${b || 'us'}! We'll update you once it's on the way.`,
  thank_you: (n, b, x) => `Hi ${n || 'there'}, thank you so much for choosing ${b || 'us'}! We really appreciate your business${x ? ' — ' + x : ''}.`,
};
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const type = document.getElementById('f_type').value;
    const name = document.getElementById('f_name').value.trim();
    const business = document.getElementById('f_business').value.trim();
    const extra = document.getElementById('f_extra').value.trim();
    const phone = document.getElementById('f_phone').value.trim();

    const message = tpTemplates[type](name, business, extra);
    let label = 'Your message — Copy it, or use the link below to open WhatsApp directly';
    let raw = message;
    if (phone) {
      let digits = phone.replace(/\D+/g, '');
      if (digits.startsWith('0')) digits = '92' + digits.slice(1);
      const link = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(message);
      raw = message + '\n\n' + link;
    }
    tpShowResult(message, { label, raw, historyLabel: 'WhatsApp Message' });
  } catch (e) { tpShowError(e.message); }
});
</script>
