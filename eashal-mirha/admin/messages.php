<?php
require __DIR__ . '/includes/admin.php';

$tab = get('tab', 'messages');
if (is_post()) {
    require_csrf();
    if (post('do') === 'delete') q('DELETE FROM messages WHERE id = ?', [(int)post('id')]);
    if (post('do') === 'read') q('UPDATE messages SET is_read = 1 - is_read WHERE id = ?', [(int)post('id')]);
    if (post('do') === 'unsub') q('DELETE FROM subscribers WHERE id = ?', [(int)post('id')]);
    back('admin/messages');
}
if ($tab === 'subscribers' && get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed']);
    foreach (rows('SELECT * FROM subscribers ORDER BY id') as $s) fputcsv($out, [$s['email'], $s['created_at']]);
    exit;
}
if ($tab === 'messages' && ($open = (int)get('open'))) q('UPDATE messages SET is_read = 1 WHERE id = ?', [$open]);

admin_header('Messages & Subscribers', 'messages');
?>
<div class="status-tabs">
  <a href="?tab=messages" class="<?= $tab === 'messages' ? 'active' : '' ?>">Contact messages <b><?= (int)val('SELECT COUNT(*) FROM messages WHERE is_read = 0') ?></b></a>
  <a href="?tab=subscribers" class="<?= $tab === 'subscribers' ? 'active' : '' ?>">Newsletter subscribers <b><?= (int)val('SELECT COUNT(*) FROM subscribers') ?></b></a>
</div>
<?php if ($tab === 'subscribers'): $subs = rows('SELECT * FROM subscribers ORDER BY id DESC'); ?>
  <div class="toolbar"><span class="muted"><?= count($subs) ?> subscribers</span><a class="btn" href="?tab=subscribers&export=csv">Export CSV</a></div>
  <div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Email</th><th>Subscribed</th><th></th></tr></thead><tbody>
    <?php foreach ($subs as $s): ?><tr><td><?= e($s['email']) ?></td><td class="muted"><?= date('d M Y', strtotime($s['created_at'])) ?></td><td class="actions"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="unsub"><button class="icon danger" name="id" value="<?= $s['id'] ?>" data-confirm="Remove this subscriber?"><?= aicon('trash') ?></button></form></td></tr><?php endforeach; ?>
    <?php if (!$subs): ?><tr><td colspan="3" class="empty">No subscribers yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php else: $msgs = rows('SELECT * FROM messages ORDER BY id DESC LIMIT 300'); ?>
  <div class="card">
    <?php foreach ($msgs as $m): ?>
      <details class="msg<?= $m['is_read'] ? '' : ' unread' ?>" <?= (int)get('open') === (int)$m['id'] ? 'open' : '' ?>>
        <summary><strong><?= e($m['name']) ?></strong><span><?= e($m['subject'] ?: excerpt($m['message'], 70)) ?></span><time><?= date('d M, h:i A', strtotime($m['created_at'])) ?></time></summary>
        <div class="msg__body">
          <p class="muted"><?= e($m['email']) ?> · <?= e($m['phone']) ?></p>
          <p><?= nl2br(e($m['message'])) ?></p>
          <form method="post" class="btn-row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
            <?php if ($m['email']): ?><a class="btn btn-sm btn-primary" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Your enquiry')) ?>">Reply by email</a><?php endif; ?>
            <?php if ($m['phone']): ?><a class="btn btn-sm" target="_blank" href="https://wa.me/<?= e(preg_replace('~^0~', '92', preg_replace('~\D~', '', $m['phone']))) ?>">WhatsApp</a><?php endif; ?>
            <button class="btn btn-sm" name="do" value="read"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
            <button class="btn btn-sm btn-danger" name="do" value="delete" data-confirm="Delete this message?">Delete</button>
          </form>
        </div>
      </details>
    <?php endforeach; ?>
    <?php if (!$msgs): ?><p class="empty">No messages yet.</p><?php endif; ?>
  </div>
<?php endif;
admin_footer();
