<?php
require __DIR__ . '/includes/init.php';
if ($open = input_int('open')) {
    $n = db_row('SELECT * FROM notifications WHERE id = ?', [$open]);
    if ($n) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ?', [$open]);
        if ($n['link']) redirect('admin/' . ltrim($n['link'], '/'));
    }
    redirect('admin/notifications.php');
}
if (is_post()) {
    csrf_check();
    if (input('act') === 'read_all') db_exec('UPDATE notifications SET is_read = 1');
    if (input('act') === 'clear_read') db_exec('DELETE FROM notifications WHERE is_read = 1');
    redirect('admin/notifications.php');
}
$pg = paginate((int) db_val('SELECT COUNT(*) FROM notifications'), 30);
$rows = db_all('SELECT * FROM notifications ORDER BY id DESC LIMIT 30 OFFSET ' . $pg['offset']);
admin_header('Notifications');
$icons = ['new_order' => 'bi-bag-check text-primary', 'rashan_order' => 'bi-basket2 text-success', 'low_stock' => 'bi-exclamation-triangle text-warning', 'out_of_stock' => 'bi-x-octagon text-danger', 'review' => 'bi-star text-warning', 'contact' => 'bi-envelope text-info'];
?>
<div class="d-flex gap-2 mb-3">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="read_all"><button class="btn btn-sm btn-outline-secondary">Mark all read</button></form>
  <form method="post" data-confirm="Delete all read notifications?"><?= csrf_field() ?><input type="hidden" name="act" value="clear_read"><button class="btn btn-sm btn-outline-danger">Clear read</button></form>
</div>
<div class="card"><div class="list-group list-group-flush">
<?php foreach ($rows as $n): ?>
  <a href="?open=<?= (int) $n['id'] ?>" class="list-group-item list-group-item-action d-flex gap-3 <?= $n['is_read'] ? '' : 'bg-light fw-semibold' ?>">
    <i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?> fs-4"></i>
    <div class="flex-grow-1"><?= e($n['title']) ?><div class="small text-muted fw-normal"><?= e($n['message']) ?></div></div>
    <small class="text-muted text-nowrap"><?= time_ago($n['created_at']) ?></small>
  </a>
<?php endforeach; if (!$rows): ?><div class="p-4 text-center text-muted">No notifications.</div><?php endif; ?>
</div></div>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
