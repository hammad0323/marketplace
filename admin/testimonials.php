<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.testimonials');
$editId = input_int('edit', 0, 'get');
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    if (input('action') === 'delete') {
        db_exec('DELETE FROM testimonials WHERE id = ?', [$id]);
        audit_log('testimonial_deleted', 'testimonial', $id);
        flash('success', 'Testimonial deleted.');
        redirect(admin_url('testimonials'));
    }
    $d = [
        'author_name' => mb_substr(input('author_name'), 0, 120), 'author_meta' => mb_substr(input('author_meta'), 0, 120) ?: null,
        'quote' => mb_substr(input('quote'), 0, 2000), 'rating' => input_int('rating') >= 1 && input_int('rating') <= 5 ? input_int('rating') : null,
        'source_note' => mb_substr(input('source_note'), 0, 255) ?: null, 'is_active' => input_bool('is_active'), 'is_sample' => 0,
    ];
    $orderNo = input('order_number');
    $d['order_id'] = $orderNo !== '' ? (db_val('SELECT id FROM orders WHERE order_number = ?', [$orderNo]) ?: null) : null;
    if ($d['author_name'] === '' || mb_strlen($d['quote']) < 5) {
        flash('error', 'Name and quote are required.');
    } elseif ($orderNo !== '' && !$d['order_id']) {
        flash('error', 'Order number not found.');
    } else {
        if ($id) {
            db_update('testimonials', $d, 'id = ?', [$id]);
        } else {
            $d['sort_order'] = (int) db_val('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM testimonials');
            $id = db_insert('testimonials', $d);
        }
        audit_log('testimonial_saved', 'testimonial', $id);
        flash('success', 'Testimonial saved.');
    }
    redirect(admin_url('testimonials'));
}
$rows = db_all('SELECT t.*, o.order_number FROM testimonials t LEFT JOIN orders o ON o.id = t.order_id ORDER BY t.sort_order, t.id');
$edit = $editId ? db_one('SELECT t.*, o.order_number FROM testimonials t LEFT JOIN orders o ON o.id = t.order_id WHERE t.id = ?', [$editId]) : null;
admin_header('Testimonials', 'testimonials');
?>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Publish only genuine customer feedback you have permission to use. Linking an order number documents the source. Sample entries are only shown while <a href="<?= e(admin_url('settings')) ?>">“Show sample content”</a> is enabled.</div>
<div class="row g-3">
  <div class="col-lg-7"><div data-sortable="testimonials">
    <?php foreach ($rows as $t): ?>
      <div class="section-row" data-id="<?= (int) $t['id'] ?>"><i class="bi bi-grip-vertical drag-handle"></i>
        <div class="flex-grow-1"><strong><?= e($t['author_name']) ?></strong> <?= $t['is_sample'] ? '<span class="badge text-bg-warning">Sample</span>' : '' ?> <?= $t['rating'] ? str_repeat('★', (int) $t['rating']) : '' ?><br><small class="text-muted">“<?= e(excerpt($t['quote'], 110)) ?>”<?= $t['order_number'] ? ' · order ' . e($t['order_number']) : '' ?></small></div>
        <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="testimonial" data-field="is_active" data-id="<?= (int) $t['id'] ?>" <?= $t['is_active'] ? 'checked' : '' ?>></div>
        <a class="btn btn-sm btn-light" href="?edit=<?= (int) $t['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" data-confirm="Delete this testimonial?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
      </div>
    <?php endforeach; ?>
  </div><?php if (!$rows): ?><p class="text-muted">No testimonials yet.</p><?php endif; ?></div>
  <div class="col-lg-5"><div class="card"><div class="card-header"><?= $edit ? 'Edit testimonial' : 'Add testimonial' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <?= f_text('author_name', 'Customer name (as they agreed to be shown)', $edit['author_name'] ?? '', ['required' => true]) ?>
      <?= f_text('author_meta', 'Detail line', $edit['author_meta'] ?? '', [], 'e.g. "Lahore · Heritage Bifold"') ?>
      <?= f_textarea('quote', 'Quote', $edit['quote'] ?? '', ['rows' => 4, 'required' => true]) ?>
      <?= f_select('rating', 'Rating', ['' => 'No rating', 5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'], $edit['rating'] ?? '') ?>
      <?= f_text('order_number', 'Linked order number (optional)', $edit['order_number'] ?? '') ?>
      <?= f_text('source_note', 'Source note (internal)', $edit['source_note'] ?? '', [], 'e.g. "Email 12 Oct, permission given"') ?>
      <?= f_check('is_active', 'Show on website', (int) ($edit['is_active'] ?? 1)) ?>
      <?= f_submit() ?> <?php if ($edit): ?><a href="<?= e(admin_url('testimonials')) ?>" class="btn btn-light">Cancel</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php admin_footer();
