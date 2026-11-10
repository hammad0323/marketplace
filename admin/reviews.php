<?php
require __DIR__ . '/includes/init.php';
admin_require('reviews');
function review_recalc(int $pid): void
{
    $r = db_row("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE product_id = ? AND status = 'approved'", [$pid]);
    db_exec('UPDATE products SET rating_avg = ?, rating_count = ? WHERE id = ?', [round((float) $r['a'], 2), (int) $r['c'], $pid]);
}
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $rev = db_row('SELECT * FROM reviews WHERE id = ?', [$id]);
    if ($rev) {
        $act = input('act');
        if (in_array($act, ['approved', 'rejected', 'pending'], true)) db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$act, $id]);
        if ($act === 'reply') db_exec('UPDATE reviews SET admin_reply = ? WHERE id = ?', [input('reply') ?: null, $id]);
        if ($act === 'delete') db_exec('DELETE FROM reviews WHERE id = ?', [$id]);
        review_recalc((int) $rev['product_id']);
        flash('success', 'Review updated.');
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/reviews.php');
}
$status = input('status', 'pending');
$params = [];
$w = in_array($status, ['pending', 'approved', 'rejected'], true) ? 'WHERE r.status = ?' : '';
if ($w) $params[] = $status;
$pg = paginate((int) db_val("SELECT COUNT(*) FROM reviews r $w", $params), 25);
$rows = db_all("SELECT r.*, p.name AS product, p.slug FROM reviews r JOIN products p ON p.id = r.product_id $w ORDER BY r.id DESC LIMIT 25 OFFSET {$pg['offset']}", $params);
admin_header('Reviews & Ratings', 'reviews');
?>
<ul class="nav nav-pills mb-3"><?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l): ?><li class="nav-item"><a class="nav-link <?= $status === $k ? 'active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<?php foreach ($rows as $r): ?>
<div class="card mb-2"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2"><div><span class="text-warning"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span> <strong><?= e($r['title']) ?></strong>
    <div class="small text-muted"><?= e($r['reviewer_name']) ?> on <a href="<?= url('product/' . $r['slug'] . '/') ?>" target="_blank"><?= e($r['product']) ?></a> · <?= fmt_datetime($r['created_at']) ?></div></div>
    <div class="d-flex gap-1"><?php foreach (['approved' => 'success', 'rejected' => 'warning'] as $st => $cl): if ($r['status'] !== $st): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="act" value="<?= $st ?>"><button class="btn btn-sm btn-outline-<?= $cl ?>"><?= $st === 'approved' ? 'Approve' : 'Reject' ?></button></form><?php endif; endforeach; ?>
      <form method="post" data-confirm="Delete review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="act" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></div>
  <p class="mt-2 mb-2"><?= nl2br(e($r['body'])) ?></p>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="act" value="reply"><input name="reply" class="form-control form-control-sm" placeholder="Public reply from Fahim Store" value="<?= e($r['admin_reply']) ?>"><button class="btn btn-sm btn-light">Save reply</button></form>
</div></div>
<?php endforeach; if (!$rows): ?><div class="card card-body text-muted">No reviews here.</div><?php endif; ?>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
