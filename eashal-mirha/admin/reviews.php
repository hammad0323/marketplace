<?php
require __DIR__ . '/includes/admin.php';

if (is_post()) {
    require_csrf();
    $rid = (int)post('id');
    switch (post('do')) {
        case 'approve': q('UPDATE reviews SET status = 1 WHERE id = ?', [$rid]); flash('success', 'Review approved.'); break;
        case 'hide':    q('UPDATE reviews SET status = 0 WHERE id = ?', [$rid]); flash('success', 'Review hidden.'); break;
        case 'delete':  q('DELETE FROM reviews WHERE id = ?', [$rid]); flash('success', 'Review deleted.'); break;
        case 'add':
            $pid = (int)post('product_id');
            if ($pid && post('name') !== '') {
                q('INSERT INTO reviews (product_id, name, rating, comment, status) VALUES (?, ?, ?, ?, 1)', [$pid, post('name'), max(1, min(5, (int)post('rating'))), post('comment')]);
                flash('success', 'Review added.');
            }
            break;
    }
    back('admin/reviews');
}

$filter = get('status', 'pending');
$where = $filter === 'pending' ? 'r.status = 0' : ($filter === 'approved' ? 'r.status = 1' : '1');
$reviews = rows("SELECT r.*, p.name product, p.slug FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE $where ORDER BY r.id DESC LIMIT 200");
$products = rows('SELECT id, name FROM products ORDER BY name');
admin_header('Reviews', 'reviews');
?>
<div class="status-tabs">
  <?php foreach (['pending' => 'Awaiting approval', 'approved' => 'Approved', 'all' => 'All'] as $k => $l): ?><a href="?status=<?= $k ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= $l ?></a><?php endforeach; ?>
</div>
<div class="grid-2 wide-left">
  <div class="card">
    <?php foreach ($reviews as $r): ?>
      <div class="review-row">
        <div><strong><?= e($r['name']) ?></strong> <span class="stars"><?= str_repeat('★', (int)$r['rating']) ?><span class="muted"><?= str_repeat('★', 5 - (int)$r['rating']) ?></span></span>
          <small class="block muted">on <a href="<?= url('product/' . $r['slug']) ?>" target="_blank"><?= e($r['product']) ?></a> · <?= date('d M Y', strtotime($r['created_at'])) ?></small>
          <p><?= nl2br(e($r['comment'])) ?></p></div>
        <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <?php if (!$r['status']): ?><button class="btn btn-sm btn-primary" name="do" value="approve">Approve</button><?php else: ?><button class="btn btn-sm" name="do" value="hide">Hide</button><?php endif; ?>
          <button class="icon danger" name="do" value="delete" data-confirm="Delete this review?"><?= aicon('trash') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><p class="empty">No reviews here.</p><?php endif; ?>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="card__head"><h3>Add a review manually</h3></div>
    <label class="field"><span>Product</span><select name="product_id" required><option value="">Choose…</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
    <?= f_text('name', 'Customer name', '', ['attrs' => 'required']) ?>
    <?= f_select('rating', 'Rating', 5, [5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']) ?>
    <?= f_text('comment', 'Review', '', ['type' => 'textarea', 'rows' => 4]) ?>
    <button class="btn btn-primary btn-block">Add Review</button>
  </form>
</div>
<?php admin_footer();
