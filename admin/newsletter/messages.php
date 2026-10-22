<?php
require __DIR__ . '/../includes/init.php';
admin_require('newsletter');
if (is_post()) {
    $id = input_int('id');
    if (input('action', '', 'post') === 'delete') db_exec('DELETE FROM contact_messages WHERE id = ?', [$id]);
    else db_exec('UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?', [$id]);
    redirect('admin/newsletter/messages.php');
}
$total = (int) db_val('SELECT COUNT(*) FROM contact_messages');
$pg = paginate($total, 25);
$rows = db_all("SELECT * FROM contact_messages ORDER BY is_read, id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
admin_header('Contact messages');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/newsletter/')) ?>">Subscribers</a></li><li class="nav-item"><a class="nav-link active" href="#">Contact messages</a></li></ul>
<?php foreach ($rows as $m): ?>
<div class="card a-card<?= (int) $m['is_read'] ? '' : ' border-warning' ?>"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2"><div><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Your message')) ?>"><?= e($m['email']) ?></a> <?= $m['phone'] ? '· ' . e($m['phone']) : '' ?>
        <div class="small text-muted"><?= e(format_datetime($m['created_at'])) ?><?= $m['subject'] ? ' · ' . e($m['subject']) : '' ?></div></div>
        <div class="d-flex gap-1"><?= a_post_button(url('admin/newsletter/messages.php'), ['action' => 'read', 'id' => $m['id']], (int) $m['is_read'] ? 'Mark unread' : 'Mark read', 'btn btn-sm btn-outline-dark', 'Change read status?') ?>
        <?= a_post_button(url('admin/newsletter/messages.php'), ['action' => 'delete', 'id' => $m['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete this message?') ?></div></div>
    <p class="mt-2 mb-0"><?= nl2br(e($m['message'])) ?></p>
</div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="a-empty"><i class="bi bi-envelope"></i><p>No messages yet.</p></div><?php endif; ?>
<?= pagination_links($pg, url('admin/newsletter/messages.php')) ?>
<?php admin_footer();
