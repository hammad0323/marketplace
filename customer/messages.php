<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$conversation = $conversationId ? db_select_one(
    $conn,
    'SELECT c.*, p.business_name, p.slug AS provider_slug, p.logo FROM conversations c JOIN providers p ON p.id = c.provider_id WHERE c.id = ? AND c.customer_id = ?',
    [$conversationId, (int) $user['id']]
) : null;

$conversations = db_select(
    $conn,
    'SELECT c.*, p.business_name, p.logo,
        (SELECT message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
     FROM conversations c JOIN providers p ON p.id = c.provider_id
     WHERE c.customer_id = ? ORDER BY c.last_message_at DESC',
    [(int) $user['id'], (int) $user['id']]
);

$threadMessages = [];
if ($conversation) {
    $threadMessages = db_select($conn, 'SELECT * FROM messages WHERE conversation_id = ? ORDER BY id ASC', [$conversationId]);
    db_execute($conn, 'UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?', [$conversationId, (int) $user['id']]);
}

$pageTitle = 'Messages';
$customerActiveTab = 'dashboard';
$extraJs = '<script src="' . ASSETS_URL . '/js/messages.js"></script>';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-chat-dots"></i> Customer</span>
      <h1 class="section-heading">Messages</h1>
    </div>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;min-height:520px;">
      <div class="panel" style="padding:0;overflow:hidden;">
        <?php if ($conversations): ?>
          <?php foreach ($conversations as $c): ?>
            <a href="?conversation_id=<?php echo (int) $c['id']; ?>" style="display:block;padding:14px 16px;border-bottom:1px solid var(--border);<?php echo $conversationId === (int) $c['id'] ? 'background:var(--purple-50);' : ''; ?>">
              <div style="display:flex;justify-content:space-between;">
                <strong style="font-size:14px;"><?php echo e($c['business_name']); ?></strong>
                <?php if ($c['unread'] > 0): ?><span style="background:var(--purple);color:#fff;font-size:10px;font-weight:700;border-radius:999px;padding:2px 7px;"><?php echo (int) $c['unread']; ?></span><?php endif; ?>
              </div>
              <div style="font-size:12.5px;color:var(--ink-mute);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo e($c['last_message'] ?? 'Start the conversation'); ?></div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-chat-dots"></i></div><h4>No conversations yet</h4><p>Message a provider from any listing page.</p></div>
        <?php endif; ?>
      </div>

      <div class="panel" style="display:flex;flex-direction:column;padding:0;">
        <?php if ($conversation): ?>
          <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;">
            <a href="<?php echo url('/pages/provider.php'); ?>?slug=<?php echo e($conversation['provider_slug']); ?>" style="color:var(--ink);"><?php echo e($conversation['business_name']); ?></a>
          </div>
          <div id="thread" data-conversation="<?php echo $conversationId; ?>" data-csrf="<?php echo e(csrf_token()); ?>" style="flex:1;overflow-y:auto;padding:16px;max-height:420px;">
            <?php foreach ($threadMessages as $m): $mine = (int) $m['sender_id'] === (int) $user['id']; ?>
              <div style="display:flex;justify-content:<?php echo $mine ? 'flex-end' : 'flex-start'; ?>;margin-bottom:10px;">
                <div style="max-width:70%;padding:10px 14px;border-radius:14px;font-size:13.5px;<?php echo $mine ? 'background:var(--gradient-purple);color:#fff;' : 'background:var(--bg);color:var(--ink);'; ?>">
                  <?php echo e($m['message_text']); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <form id="message-form" style="display:flex;gap:8px;padding:14px;border-top:1px solid var(--border);">
            <input type="text" name="message_text" placeholder="Type a message…" autocomplete="off" style="flex:1;padding:11px 16px;border-radius:999px;border:1.5px solid var(--border);">
            <button type="submit" class="btn-w btn-primary btn-sm"><i class="bi bi-send"></i></button>
          </form>
        <?php else: ?>
          <div class="empty-state" style="padding:60px;margin:auto;"><div class="icon-wrap"><i class="bi bi-chat-square-text"></i></div><h4>Select a conversation</h4></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
