<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('messages.view');
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    $st = input('status');
    if (input('action') === 'delete') {
        db_exec('DELETE FROM contact_messages WHERE id = ?', [$id]);
    } elseif (in_array($st, ['new', 'read', 'replied', 'archived'], true)) {
        db_exec('UPDATE contact_messages SET status = ? WHERE id = ?', [$st, $id]);
    }
    admin_back('messages');
}
$filter = in_array(input('status', 'open', 'get'), ['open', 'archived', 'all'], true) ? input('status', 'open', 'get') : 'open';
$where = ['open' => "status IN ('new','read','replied')", 'archived' => "status = 'archived'", 'all' => '1=1'][$filter];
$rows = db_all("SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT 200");
admin_header('Messages', 'messages');
?>
<ul class="nav nav-pills mb-3"><?php foreach (['open' => 'Open', 'archived' => 'Archived', 'all' => 'All'] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $filter === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<?php foreach ($rows as $m): ?>
  <div class="card mb-2"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong><?= e($m['subject']) ?></strong> <?= badge($m['status']) ?><br><small class="text-muted"><?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= e($m['email']) ?></a> <?= e($m['phone']) ?> · <?= e(format_date($m['created_at'], true)) ?></small></div>
      <div class="d-flex gap-1"><?php foreach (['read' => 'Mark read', 'replied' => 'Mark replied', 'archived' => 'Archive'] as $s => $l): if ($m['status'] === $s) continue; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="status" value="<?= $s ?>"><button class="btn btn-sm btn-light"><?= $l ?></button></form><?php endforeach; ?>
        <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div></div>
    <p class="mt-2 mb-0"><?= nl2br(e($m['message'])) ?></p>
  </div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted">No messages.</p><?php endif; ?>
<?php admin_footer();
