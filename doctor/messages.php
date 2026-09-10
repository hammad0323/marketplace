<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$pageTitle = 'Messages';
$heading = 'Messages';
$extraScripts = '<script defer src="/assets/js/chat.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="chat-shell" id="chat-shell">
    <div class="chat-list">
        <div style="padding:16px;border-bottom:1px solid var(--color-border);font-weight:700;">Patients</div>
        <div id="chat-list-items"></div>
    </div>
    <div class="chat-main" id="chat-main" style="display:none;grid-column:2;">
        <div class="chat-head">
            <button class="btn-icon" id="chat-back-btn" style="display:none;"><i class="ri-arrow-left-line"></i></button>
            <img id="chat-partner-avatar" src="" alt="">
            <div style="flex:1;min-width:0;">
                <span style="display:flex;align-items:center;gap:6px;">
                    <strong id="chat-partner-name" style="font-size:14.5px;"></strong>
                    <button type="button" class="btn-icon" id="chat-rename-btn" style="width:26px;height:26px;flex-shrink:0;" title="Rename patient"><i class="ri-pencil-line" style="font-size:13px;"></i></button>
                </span>
                <span id="chat-partner-status" style="font-size:12px;color:var(--color-text-muted);"></span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="chat-block-btn" style="flex-shrink:0;">Block</button>
        </div>
        <div class="chat-thread" id="chat-thread"></div>
        <div id="chat-blocked-notice" style="display:none;padding:14px 20px;text-align:center;font-size:13px;color:var(--color-text-muted);border-top:1px solid var(--color-border);">
            You've blocked this patient — they can't send you new messages. <button type="button" id="chat-unblock-inline-btn" style="background:none;border:none;color:var(--color-primary);font-weight:600;text-decoration:underline;cursor:pointer;padding:0;">Unblock</button>
        </div>
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
        <p>Select a patient conversation from the list.</p>
        <p style="font-size:12.5px;">Enable messaging and set your hours in <a href="/doctor/profile" style="color:var(--color-primary);font-weight:600;">Profile → Messaging</a>.</p>
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
