<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('reviews.manage');
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    $action = input('action');
    if (in_array($action, ['approved', 'rejected', 'pending'], true)) {
        db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$action, $id]);
        audit_log('review_' . $action, 'review', $id);
    } elseif ($action === 'reply') {
        db_exec('UPDATE reviews SET admin_reply = ? WHERE id = ?', [mb_substr(input('reply'), 0, 2000) ?: null, $id]);
        audit_log('review_reply', 'review', $id);
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM reviews WHERE id = ?', [$id]);
        audit_log('review_deleted', 'review', $id);
    }
    flash('success', 'Review updated.');
    admin_back('reviews');
}
$status = in_array(input('status', 'pending', 'get'), ['pending', 'approved', 'rejected', 'all'], true) ? input('status', 'pending', 'get') : 'pending';
$rows = db_all('SELECT r.*, p.name product_name, p.slug FROM reviews r JOIN products p ON p.id = r.product_id' . ($status !== 'all' ? ' WHERE r.status = ?' : '') . ' ORDER BY r.created_at DESC LIMIT 200', $status !== 'all' ? [$status] : []);
admin_header('Reviews', 'reviews');
?>
<ul class="nav nav-pills mb-3"><?php foreach (['pending', 'approved', 'rejected', 'all'] as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?></a></li><?php endforeach; ?></ul>
<?php foreach ($rows as $r): ?>
  <div class="card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div><strong><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></strong> <?= e($r['title']) ?> <?= badge($r['status']) ?><?= $r['is_verified_purchase'] ? ' <span class="badge text-bg-success">Verified purchase</span>' : '' ?><br>
        <small class="text-muted"><?= e($r['author_name']) ?> &lt;<?= e($r['author_email']) ?>&gt; on <a href="<?= e(path_url('product/' . $r['slug'])) ?>" target="_blank"><?= e($r['product_name']) ?></a> · <?= e(format_date($r['created_at'], true)) ?> · IP <?= e($r['ip_address']) ?></small></div>
      <div class="d-flex gap-1">
        <?php foreach (['approved' => ['success', 'check-lg', 'Approve'], 'rejected' => ['outline-secondary', 'x-lg', 'Reject']] as $a => [$cls, $icon, $label]): if ($r['status'] === $a) continue; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-sm btn-<?= $cls ?>"><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></button></form>
        <?php endforeach; ?>
        <form method="post" data-confirm="Delete this review permanently?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
      </div>
    </div>
    <p class="mt-2 mb-2"><?= nl2br(e($r['body'])) ?></p>
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reply">
      <input class="form-control form-control-sm" name="reply" value="<?= e($r['admin_reply']) ?>" placeholder="Public reply (optional)"><button class="btn btn-sm btn-outline-primary">Save reply</button></form>
  </div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted">No reviews in this view.</p><?php endif; ?>
<?php admin_footer();
