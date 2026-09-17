<?php
require __DIR__ . '/../config.php';
wh_require_page_access('chatbot');
$businessId = wh_current_business_id();

$recentLogs = wh_fetch_all('SELECT * FROM chatbot_logs WHERE business_id=? ORDER BY id DESC LIMIT 15', 'i', [$businessId]);

$pageTitle = 'Database Assistant';
$activePage = 'chatbot';
require __DIR__ . '/header.php';
?>
<div class="two-col">
  <div class="admin-card" style="padding:0;">
    <div class="chat-window">
      <div class="chat-messages" id="chatMessages">
        <div class="chat-bubble bot">Assalam-o-Alaikum! Main aapka Booking Database Assistant hoon. Aap mujh se availability, bookings, payments ke baare mein English, Urdu ya Roman Urdu mein pooch sakte hain.

Example: "25 Dec ko Hall A free hai?"</div>
      </div>
      <div class="chat-quick" id="quickActions">
        <button type="button" class="btn btn-light btn-sm" data-q="Aaj ki bookings kya hain?">Today's Bookings</button>
        <button type="button" class="btn btn-light btn-sm" data-q="Kal kon sa event hai?">Tomorrow's Bookings</button>
        <button type="button" class="btn btn-light btn-sm" data-q="Kitni payment pending hai?">Pending Payments</button>
        <button type="button" class="btn btn-light btn-sm" data-q="Is month kitna advance receive hua?">This Month's Revenue</button>
        <button type="button" class="btn btn-light btn-sm" data-q="Is month kitni bookings hain?">This Month's Bookings</button>
      </div>
      <form class="chat-input-row" id="chatForm">
        <?= wh_csrf_field() ?>
        <input type="text" name="question" id="chatInput" placeholder="Type your question in English, Urdu or Roman Urdu…" autocomplete="off" required>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Recent Questions</h3>
    <?php foreach ($recentLogs as $log): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--a-border);">
        <div style="font-weight:600;font-size:.85rem;"><?= e($log['question']) ?></div>
        <div class="hint"><?= e(mb_strimwidth($log['answer'], 0, 80, '…')) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentLogs): ?><p class="hint">No questions asked yet.</p><?php endif; ?>
  </div>
</div>

<script>
var chatMessages = document.getElementById('chatMessages');
function addBubble(text, cls) {
  var div = document.createElement('div');
  div.className = 'chat-bubble ' + cls;
  div.textContent = text;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}
function askQuestion(q) {
  addBubble(q, 'user');
  var fd = new FormData();
  fd.append('csrf_token', '<?= e(wh_csrf_token()) ?>');
  fd.append('question', q);
  fetch('<?= e(BASE_URL) ?>/ajax/chatbot-query.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (data) {
    addBubble(data.success ? data.answer : (data.message || 'Something went wrong.'), 'bot');
  });
}
document.getElementById('chatForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var input = document.getElementById('chatInput');
  if (!input.value.trim()) return;
  askQuestion(input.value.trim());
  input.value = '';
});
document.querySelectorAll('#quickActions [data-q]').forEach(function (btn) {
  btn.addEventListener('click', function () { askQuestion(btn.getAttribute('data-q')); });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
