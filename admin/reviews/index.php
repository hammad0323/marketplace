<?php
/** Review moderation. Featured approved reviews appear in the homepage testimonials section. */
require __DIR__ . '/../includes/init.php';
admin_require('reviews');
if (is_post()) {
    $rid = input_int('id');
    $r = db_row('SELECT * FROM reviews WHERE id = ?', [$rid]);
    if ($r) {
        $a = input('action', '', 'post');
        if (in_array($a, ['approved', 'rejected', 'pending'], true)) {
            db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$a, $rid]);
        } elseif ($a === 'feature') {
            db_exec('UPDATE reviews SET is_featured = 1 - is_featured WHERE id = ?', [$rid]);
        } elseif ($a === 'reply') {
            db_exec('UPDATE reviews SET admin_reply = ? WHERE id = ?', [nullable(mb_substr(input('reply', '', 'post'), 0, 2000)), $rid]);
        } elseif ($a === 'delete') {
            db_exec('DELETE FROM reviews WHERE id = ?', [$rid]);
        }
        product_rating_refresh((int) $r['product_id']);
        audit_log('review_' . $a, 'review', $rid);
        flash('success', 'Review updated.');
    }
    redirect_back('admin/reviews/');
}
$status = input('status', '', 'get');
$where = in_array($status, ['pending', 'approved', 'rejected'], true) ? 'r.status = ?' : '1';
$params = $where === '1' ? [] : [$status];
$total = (int) db_val("SELECT COUNT(*) FROM reviews r WHERE $where", $params);
$pg = paginate($total, 25);
$rows = db_all("SELECT r.*, p.name pname, p.slug FROM reviews r JOIN products p ON p.id = r.product_id WHERE $where ORDER BY r.status = 'pending' DESC, r.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Reviews & testimonials');
?>
<div class="a-help mb-3">Only genuine customer reviews are shown. Approve reviews to publish them on product pages, and mark the best ones as <b>Featured</b> to show them in the homepage “What Our Customers Say” section.</div>
<ul class="nav a-tabs"><?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<?php foreach ($rows as $r): ?>
<div class="card a-card"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 justify-content-between">
        <div><strong><?= e($r['author_name']) ?></strong><?= $r['author_city'] ? ', ' . e($r['author_city']) : '' ?> · <?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?>
            <?= (int) $r['is_verified_purchase'] ? a_badge('Verified purchase', 'success') : '' ?> <?= a_badge(ucfirst($r['status']), ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary'][$r['status']]) ?> <?= (int) $r['is_featured'] ? a_badge('Featured', 'dark') : '' ?>
            <div class="small text-muted">on <a href="<?= e(url('product/' . $r['slug'])) ?>" target="_blank"><?= e($r['pname']) ?></a> · <?= e(format_datetime($r['created_at'])) ?></div></div>
        <div class="d-flex gap-1 flex-wrap">
            <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $k => $l): if ($r['status'] === $k) continue; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="<?= $k ?>"><button class="btn btn-sm <?= $k === 'approved' ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $l ?></button></form>
            <?php endforeach; ?>
            <?php if ($r['status'] === 'approved'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="feature"><button class="btn btn-sm btn-outline-dark"><?= (int) $r['is_featured'] ? 'Unfeature' : 'Feature on homepage' ?></button></form><?php endif; ?>
            <?= a_post_button(url('admin/reviews/'), ['id' => $r['id'], 'action' => 'delete'], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete this review?') ?>
        </div>
    </div>
    <?php if ($r['title']): ?><p class="fw-semibold mt-2 mb-1"><?= e($r['title']) ?></p><?php endif; ?>
    <p class="mb-2"><?= nl2br(e($r['body'])) ?></p>
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reply">
        <input class="form-control form-control-sm" name="reply" value="<?= e($r['admin_reply']) ?>" placeholder="Public reply from the store (optional)"><button class="btn btn-sm btn-outline-dark">Save reply</button></form>
</div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="a-empty"><i class="bi bi-star"></i><p>No reviews yet. Reviews appear here when signed-in customers review products.</p></div><?php endif; ?>
<?= pagination_links($pg, url('admin/reviews/')) ?>
<?php admin_footer();
