<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$insights = ai_dashboard_insights($cid, current_user_id());
$recentChats = db_all("SELECT * FROM ai_logs WHERE company_id=? AND user_id=? AND feature='chat' ORDER BY created_at DESC LIMIT 10", [$cid, current_user_id()]);
$recentChats = array_reverse($recentChats);
$aiEnabled = ai_is_enabled();

$pageTitle = 'AI Assistant';
$activeMenu = 'ai-assistant';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0"><i class="bi bi-robot text-primary"></i> AI Quality Assistant</h4>
<p class="text-muted mb-0 small">Answers are generated only from <?= out(db_val("SELECT name FROM companies WHERE id=?", [$cid])) ?>'s own data. <?= $aiEnabled ? '' : '<span class="badge bg-secondary-subtle text-secondary">Running in rule-based mode (no AI provider configured by Super Admin)</span>' ?></p></div>

<div class="row g-3 mb-3">
  <?php foreach ($insights as $ins): ?>
    <div class="col-md-6"><div class="ai-suggestion-box"><i class="bi bi-stars mt-1"></i><span><?= out($ins) ?></span></div></div>
  <?php endforeach; ?>
</div>

<div class="qc-card">
  <div id="chatWindow" style="height:420px; overflow-y:auto;" class="mb-3 p-2">
    <?php if (!$recentChats): ?>
      <div class="ai-chat-bubble ai">Hi, I'm your AI Quality Assistant. Ask me about defect rates, recurring problems, overdue CAPA, supplier performance or anything else in your quality data.</div>
    <?php endif; ?>
    <?php foreach ($recentChats as $c): ?>
      <div class="ai-chat-bubble user"><?= out($c['prompt']) ?></div>
      <div class="ai-chat-bubble ai"><?= out($c['response']) ?></div>
    <?php endforeach; ?>
  </div>
  <form id="chatForm" class="d-flex gap-2">
    <input type="text" id="chatInput" class="form-control" placeholder="Ask a question about your quality data..." required>
    <button class="btn btn-primary"><i class="bi bi-send"></i></button>
  </form>
  <div class="d-flex gap-2 flex-wrap mt-2">
    <?php foreach (['Which department has the highest defect rate?','Which CAPA actions are overdue?','Which supplier has the lowest quality score?','What should we investigate first?'] as $sample): ?>
      <button type="button" class="btn btn-sm btn-soft-primary sample-q"><?= out($sample) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<?php
$extraScripts = '<script>
function appendBubble(text, cls){
  var el = document.createElement("div");
  el.className = "ai-chat-bubble " + cls;
  el.textContent = text;
  document.getElementById("chatWindow").appendChild(el);
  document.getElementById("chatWindow").scrollTop = 999999;
}
document.getElementById("chatForm").addEventListener("submit", function(e){
  e.preventDefault();
  var input = document.getElementById("chatInput");
  var q = input.value.trim();
  if (!q) return;
  appendBubble(q, "user");
  input.value = "";
  appendBubble("Thinking...", "ai");
  $.post(QMS.baseUrl + "/ajax/manager/ai-chat.php", { question: q, csrf_token: QMS.csrfToken }).done(function(res){
    document.getElementById("chatWindow").lastElementChild.remove();
    appendBubble(res.success ? res.answer : (res.message || "Something went wrong."), "ai");
  });
});
document.querySelectorAll(".sample-q").forEach(function(btn){
  btn.addEventListener("click", function(){ document.getElementById("chatInput").value = btn.textContent; document.getElementById("chatForm").requestSubmit(); });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
