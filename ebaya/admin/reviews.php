<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('reviews.manage');
if (is_post()) {
    csrf_check();
    $act = post('action');
    $rid = (int)post('id');
    if (in_array($act, ['approved', 'rejected', 'pending'], true)) {
        db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$act, $rid]);
        product_rating_refresh((int)db_val('SELECT product_id FROM reviews WHERE id = ?', [$rid]));
        audit('review_' . $act, 'review', $rid);
    } elseif ($act === 'delete_review') {
        $pid = (int)db_val('SELECT product_id FROM reviews WHERE id = ?', [$rid]);
        db_exec('DELETE FROM reviews WHERE id = ?', [$rid]);
        product_rating_refresh($pid);
        audit('review_delete', 'review', $rid);
    } elseif ($act === 'promote') {
        $r = db_one("SELECT * FROM reviews WHERE id = ? AND status = 'approved'", [$rid]);
        if ($r) {
            db_insert("INSERT INTO testimonials (name, quote, rating, product_id, review_id, status) VALUES (?, ?, ?, ?, ?, 'active')", [$r['name'], mb_substr($r['body'], 0, 600), $r['rating'], $r['product_id'], $rid]);
            flash('success', 'Added to homepage testimonials.');
        }
    } elseif ($act === 'save_testimonial') {
        $tid = (int)post('id');
        $d = [mb_substr(post('name'), 0, 120), mb_substr(post('location'), 0, 120) ?: null, mb_substr(post('quote'), 0, 1000), post('rating') !== '' ? clamp_int(post('rating'), 1, 5) : null, (int)post('product_id') ?: null, (int)post('sort_order'), post('status') === 'active' ? 'active' : 'inactive'];
        if ($d[0] === '' || $d[2] === '') { flash('danger', 'Name and quote are required.'); }
        elseif ($tid) db_exec('UPDATE testimonials SET name=?, location=?, quote=?, rating=?, product_id=?, sort_order=?, status=? WHERE id = ?', array_merge($d, [$tid]));
        else db_insert('INSERT INTO testimonials (name, location, quote, rating, product_id, sort_order, status) VALUES (?,?,?,?,?,?,?)', $d);
        audit('testimonial_save', 'testimonial', $tid ?: null);
    } elseif ($act === 'delete_testimonial') {
        db_exec('DELETE FROM testimonials WHERE id = ?', [$rid]);
    }
    redirect(admin_url('reviews' . query_with([])));
}
$status = in_list(get('status'), ['pending', 'approved', 'rejected'], 'pending');
$reviews = db_all('SELECT r.*, p.name product, p.slug FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.status = ? ORDER BY r.id DESC LIMIT 200', [$status]);
$tests = db_all('SELECT t.*, p.name product FROM testimonials t LEFT JOIN products p ON p.id = t.product_id ORDER BY sort_order, id');
$products = array_column(db_all('SELECT id, name FROM products ORDER BY name'), 'name', 'id');
$editT = get('t') !== '' ? (db_one('SELECT * FROM testimonials WHERE id = ?', [(int)get('t')]) ?: ['id' => 0]) : null;
$admin_title = 'Reviews & testimonials';
require __DIR__ . '/partials/header.php';
?>
<ul class="nav nav-pills mb-3"><?php foreach (['pending', 'approved', 'rejected'] as $s): ?><li class="nav-item"><a class="nav-link<?= $s === $status ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?> (<?= (int)db_val('SELECT COUNT(*) FROM reviews WHERE status = ?', [$s]) ?>)</a></li><?php endforeach; ?></ul>
<div class="card mb-4"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Product</th><th>Rating</th><th>Review</th><th>By</th><th></th></tr></thead>
  <tbody><?php foreach ($reviews as $r): ?>
    <tr><td><a href="<?= e(url('product/' . $r['slug'])) ?>" target="_blank"><?= e($r['product']) ?></a></td><td><?= str_repeat('★', (int)$r['rating']) ?></td>
      <td><strong><?= e($r['title']) ?></strong><div class="small"><?= nl2br(e($r['body'])) ?></div></td><td class="small"><?= e($r['name']) ?><?= $r['verified_purchase'] ? '<br><span class="text-success">Verified</span>' : '' ?><br><?= e(substr($r['created_at'], 0, 10)) ?></td>
      <td class="text-nowrap"><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if ($r['status'] !== 'approved'): ?><button name="action" value="approved" class="btn btn-sm btn-success">Approve</button><?php endif; ?>
        <?php if ($r['status'] !== 'rejected'): ?><button name="action" value="rejected" class="btn btn-sm btn-outline-secondary">Reject</button><?php endif; ?>
        <?php if ($r['status'] === 'approved'): ?><button name="action" value="promote" class="btn btn-sm btn-outline-primary" title="Use as homepage testimonial">Feature</button><?php endif; ?>
        <button name="action" value="delete_review" class="btn btn-sm btn-light text-danger" onclick="return confirm('Delete review?')"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; if (!$reviews): ?><tr><td colspan="5" class="text-muted text-center py-3">No <?= e($status) ?> reviews.</td></tr><?php endif; ?></tbody>
</table></div></div>

<div class="card"><div class="card-header d-flex">Homepage testimonials <a class="btn btn-sm btn-primary ms-auto" href="?t=0">Add testimonial</a></div>
  <div class="card-body small text-muted pb-0">Only publish genuine customer feedback, with the customer's permission. Approved reviews can be featured with one click.</div>
  <div class="table-responsive"><table class="table mb-0"><tbody>
  <?php foreach ($tests as $t): ?><tr><td><?= e($t['name']) ?><div class="small text-muted"><?= e($t['location']) ?></div></td><td class="small"><?= e(str_limit($t['quote'], 140)) ?></td><td><?= $t['rating'] ? str_repeat('★', (int)$t['rating']) : '' ?></td><td><?= status_badge($t['status']) ?></td>
    <td class="text-nowrap"><a class="btn btn-sm btn-light" href="?t=<?= (int)$t['id'] ?>"><i class="bi bi-pencil"></i></a><form method="post" class="d-inline" data-confirm="Delete testimonial?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_testimonial"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($editT !== null): $tv = fn($k, $d = '') => $editT[$k] ?? $d; ?>
  <div class="card-body border-top"><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="save_testimonial"><input type="hidden" name="id" value="<?= (int)$tv('id', 0) ?>">
    <div class="col-md-4"><?= f_text('name', 'Customer name', $tv('name'), ['required' => true]) ?></div>
    <div class="col-md-4"><?= f_text('location', 'Location', $tv('location')) ?></div>
    <div class="col-md-2"><?= f_select('rating', 'Rating', ['' => '—', 5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'], $tv('rating')) ?></div>
    <div class="col-md-2"><?= f_text('sort_order', 'Order', $tv('sort_order', 0), ['type' => 'number']) ?></div>
    <div class="col-12"><?= f_text('quote', 'Quote', $tv('quote'), ['type' => 'textarea', 'rows' => 3, 'required' => true]) ?></div>
    <div class="col-md-6"><?= f_select('product_id', 'Product (optional)', $products, $tv('product_id'), ['empty' => '—']) ?></div>
    <div class="col-md-3"><?= f_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], $tv('status', 'active')) ?></div>
    <div class="col-12"><button class="btn btn-primary">Save testimonial</button></div></form></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php';
