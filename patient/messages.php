<?php
require __DIR__ . '/../config/config.php';
require_patient_page();

$pageTitle = 'Messages';
$heading = 'Messages';
$extraScripts = '<script defer src="/assets/js/chat.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="chat-shell" id="chat-shell">
    <div class="chat-list">
        <div style="padding:16px;border-bottom:1px solid var(--color-border);font-weight:700;">Conversations</div>
        <div id="chat-list-items"></div>
    </div>
    <div class="chat-main" id="chat-main" style="display:none;grid-column:2;">
        <div class="chat-head">
            <button class="btn-icon" id="chat-back-btn" style="display:none;"><i class="ri-arrow-left-line"></i></button>
            <img id="chat-partner-avatar" src="" alt="">
            <div>
                <strong id="chat-partner-name" style="display:block;font-size:14.5px;"></strong>
                <span id="chat-partner-status" style="font-size:12px;color:var(--color-text-muted);"></span>
            </div>
        </div>
        <div class="chat-thread" id="chat-thread"></div>
        <form class="chat-input-row" id="chat-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="chat-attach-btn" for="chat-attach-input"><i class="ri-attachment-2"></i></label>
            <input type="file" id="chat-attach-input" accept="image/png,image/jpeg,image/webp,application/pdf" style="display:none;">
            <textarea id="chat-input" rows="1" placeholder="Type a message…"></textarea>
            <button type="submit" class="chat-send-btn"><i class="ri-send-plane-fill"></i></button>
        </form>
    </div>
    <div class="chat-empty" id="chat-empty-state" style="grid-column:2;">
        <i class="ri-chat-3-line" style="font-size:44px;"></i>
        <p>Select a conversation, or message a doctor from their profile page.</p>
        <a href="/doctors" class="btn btn-outline btn-sm">Find a Doctor</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.innerWidth <= 760) {
        document.getElementById('chat-shell').classList.add('show-list');
        document.getElementById('chat-back-btn').style.display = 'flex';
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
