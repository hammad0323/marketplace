<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.slides');
if (is_post()) {
    require_csrf();
    $s = db_one('SELECT * FROM banner_slides WHERE id = ?', [input_int('id')]);
    if ($s && input('action') === 'delete') {
        db_exec('DELETE FROM banner_slides WHERE id = ?', [$s['id']]);
        delete_upload($s['image_desktop']);
        delete_upload($s['image_mobile']);
        audit_log('slide_deleted', 'slide', (int) $s['id'], ['title' => $s['title']]);
        flash('success', 'Slide deleted.');
    }
    redirect(admin_url('slides'));
}
$rows = db_all('SELECT * FROM banner_slides ORDER BY sort_order, id');
admin_header('Hero slides', 'slides');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Drag to reorder. Autoplay, timing and transitions are set on the <a href="<?= e(admin_url('homepage')) ?>">Hero section</a>.</p><a class="btn btn-primary btn-sm" href="<?= e(admin_url('slide-edit')) ?>"><i class="bi bi-plus-lg"></i> Add slide</a></div>
<div data-sortable="slides">
<?php foreach ($rows as $s): ?>
  <div class="section-row" data-id="<?= (int) $s['id'] ?>"><i class="bi bi-grip-vertical drag-handle"></i>
    <img src="<?= e(media_url($s['image_desktop'])) ?>" alt="" style="width:120px;height:60px;object-fit:cover;border-radius:4px">
    <div class="flex-grow-1"><a href="<?= e(admin_url('slide-edit', ['id' => $s['id']])) ?>"><strong><?= e($s['title']) ?></strong></a><br><small class="text-muted"><?= e($s['subtitle']) ?><?= $s['starts_at'] || $s['ends_at'] ? ' · scheduled ' . e(format_date($s['starts_at'])) . ' → ' . e(format_date($s['ends_at'])) : '' ?></small></div>
    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="slide" data-field="is_active" data-id="<?= (int) $s['id'] ?>" <?= $s['is_active'] ? 'checked' : '' ?>></div>
    <a class="btn btn-sm btn-light" href="<?= e(admin_url('slide-edit', ['id' => $s['id']])) ?>"><i class="bi bi-pencil"></i></a>
    <form method="post" data-confirm="Delete this slide?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
  </div>
<?php endforeach; ?>
</div>
<?php admin_footer();
