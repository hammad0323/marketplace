<?php
require __DIR__ . '/includes/init.php';
admin_require('customers');
if (is_post()) {
    csrf_check();
    if (input('act') === 'delete') db_exec('DELETE FROM contact_messages WHERE id = ?', [input_int('id')]);
    if (input('act') === 'read') db_exec('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [input_int('id')]);
    redirect('admin/messages.php');
}
$pg = paginate((int) db_val('SELECT COUNT(*) FROM contact_messages'), 25);
$rows = db_all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 25 OFFSET ' . $pg['offset']);
admin_header('Contact Messages', 'messages');
foreach ($rows as $m): ?>
<div class="card mb-2 <?= $m['is_read'] ? '' : 'border-warning' ?>"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2"><div><strong><?= e($m['subject'] ?: 'Message') ?></strong> <?= $m['is_read'] ? '' : '<span class="badge bg-warning text-dark">New</span>' ?><div class="small text-muted"><?= e($m['name']) ?> · <?= e($m['phone']) ?> · <?= e($m['email']) ?> · <?= fmt_datetime($m['created_at']) ?></div></div>
    <div class="d-flex gap-1"><?php if ($m['phone']): ?><a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?= e(whatsapp_link($m['phone'])) ?>"><i class="bi bi-whatsapp"></i></a><?php endif; ?><?php if ($m['email']): ?><a class="btn btn-sm btn-outline-secondary" href="mailto:<?= e($m['email']) ?>"><i class="bi bi-reply"></i></a><?php endif; ?>
      <?php if (!$m['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="read"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-brand">Mark read</button></form><?php endif; ?>
      <form method="post" data-confirm="Delete message?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></div>
  <p class="mb-0 mt-2"><?= nl2br(e($m['message'])) ?></p></div></div>
<?php endforeach; if (!$rows): ?><div class="card card-body text-muted">No messages.</div><?php endif; ?>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
