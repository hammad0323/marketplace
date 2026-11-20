<?php
require __DIR__ . '/_init.php';
$a = current_admin();
if (isset($_GET['feed'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => true, 'message' => '', 'data' => notifications_for('admin', (int) $a['id'], 20, max(0, (int) ($_GET['since'] ?? 0)))]);
    exit;
}
if (is_post()) {
    notifications_mark_read('admin', (int) $a['id']);
    redirect('admin/notifications.php');
}
$n = notifications_for('admin', (int) $a['id'], 100);
admin_header('Notifications');
?>
<div class="d-flex gap-2 mb-3"><form method="post"><?= csrf_field() ?><button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all"></i> Mark all as read</button></form>
<button type="button" class="btn btn-light btn-sm" data-enable-notifications><i class="bi bi-bell"></i> Browser notifications</button></div>
<div class="p-card"><div class="list-group list-group-flush">
<?php foreach ($n['items'] as $i): ?>
    <a class="list-group-item list-group-item-action py-3<?= $i['is_read'] ? '' : ' fw-semibold' ?>" href="<?= e($i['link'] ?? '#') ?>"><div class="d-flex"><span><?= e($i['title']) ?></span><small class="ms-auto text-muted"><?= e(time_ago($i['created_at'])) ?></small></div>
    <?php if ($i['body']): ?><small class="text-muted fw-normal"><?= e($i['body']) ?></small><?php endif; ?></a>
<?php endforeach; ?>
<?php if (!$n['items']): ?><div class="p-empty"><i class="bi bi-bell-slash"></i>No notifications yet.</div><?php endif; ?>
</div></div>
<?php admin_footer();
