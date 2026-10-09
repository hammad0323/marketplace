<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('collections.manage');
if (is_post()) {
    require_csrf();
    $c = db_one('SELECT * FROM collections WHERE id = ?', [input_int('id')]);
    if ($c && input('action') === 'delete') {
        db_exec('DELETE FROM collections WHERE id = ?', [$c['id']]);
        delete_upload($c['image']);
        audit_log('collection_deleted', 'collection', (int) $c['id'], ['name' => $c['name']]);
        flash('success', 'Collection deleted (products are unaffected).');
    }
    redirect(admin_url('collections'));
}
$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.id) n FROM collections c ORDER BY sort_order, name');
admin_header('Collections', 'collections');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Curated groups of products, e.g. "The Noir Edit". Featured on the homepage via the homepage builder.</p><a class="btn btn-primary btn-sm" href="<?= e(admin_url('collection-edit')) ?>"><i class="bi bi-plus-lg"></i> Add collection</a></div>
<div data-sortable="collections">
<?php foreach ($rows as $c): ?>
  <div class="section-row" data-id="<?= (int) $c['id'] ?>">
    <i class="bi bi-grip-vertical drag-handle"></i><img class="thumb" src="<?= e(media_url($c['image'])) ?>" alt="">
    <div class="flex-grow-1"><a href="<?= e(admin_url('collection-edit', ['id' => $c['id']])) ?>"><strong><?= e($c['name']) ?></strong></a> <small class="text-muted">/collection/<?= e($c['slug']) ?> · <?= (int) $c['n'] ?> products</small></div>
    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="collection" data-field="is_active" data-id="<?= (int) $c['id'] ?>" <?= $c['is_active'] ? 'checked' : '' ?>></div>
    <a class="btn btn-sm btn-light" href="<?= e(admin_url('collection-edit', ['id' => $c['id']])) ?>"><i class="bi bi-pencil"></i></a>
    <form method="post" data-confirm="Delete this collection?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><p class="text-muted">No collections yet.</p><?php endif; ?>
<?php admin_footer();
