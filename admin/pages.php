<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.pages');
if (is_post()) {
    require_csrf();
    $p = db_one('SELECT * FROM pages WHERE id = ?', [input_int('id')]);
    if ($p && input('action') === 'delete') {
        db_exec('DELETE FROM pages WHERE id = ?', [$p['id']]);
        audit_log('page_deleted', 'page', (int) $p['id'], ['slug' => $p['slug']]);
        flash('success', 'Page deleted. Consider adding a redirect for /' . $p['slug'] . '.');
    }
    redirect(admin_url('pages'));
}
$rows = db_all('SELECT * FROM pages ORDER BY footer_group, sort_order, title');
admin_header('Pages', 'pages');
?>
<div class="d-flex justify-content-end mb-3"><a class="btn btn-primary btn-sm" href="<?= e(admin_url('page-edit')) ?>"><i class="bi bi-plus-lg"></i> Add page</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Title</th><th>URL</th><th>Footer column</th><th>Published</th><th>Updated</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $p): ?>
    <tr><td><a href="<?= e(admin_url('page-edit', ['id' => $p['id']])) ?>"><strong><?= e($p['title']) ?></strong></a><?= $p['noindex'] ? ' <span class="badge text-bg-light">noindex</span>' : '' ?></td><td><a href="<?= e(path_url($p['slug'])) ?>" target="_blank">/<?= e($p['slug']) ?></a></td><td><?= e(ucfirst($p['footer_group'])) ?></td>
      <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="page" data-field="is_published" data-id="<?= (int) $p['id'] ?>" <?= $p['is_published'] ? 'checked' : '' ?>></div></td><td><small><?= e(format_date($p['updated_at'])) ?></small></td>
      <td class="text-end"><a class="btn btn-sm btn-light" href="<?= e(admin_url('page-edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-inline" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php admin_footer();
