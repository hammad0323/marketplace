<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('seo.manage');
$errors = [];
if (is_post()) {
    require_csrf();
    if (input('action') === 'delete') {
        db_exec('DELETE FROM redirects WHERE id = ?', [input_int('id')]);
        audit_log('redirect_deleted', 'redirect', input_int('id'));
        redirect(admin_url('redirects'));
    }
    $from = '/' . ltrim(trim(parse_url(input('source_path'), PHP_URL_PATH) ?? ''), '/');
    $to = trim(input('target_path'));
    $code = in_array(input_int('status_code'), [301, 302, 307, 308], true) ? input_int('status_code') : 301;
    if ($from === '/' || strlen($from) > 255) {
        $errors[] = 'Enter the old path, e.g. /old-product-url';
    }
    if ($to === '' || (!preg_match('#^https?://#', $to) && $to[0] !== '/')) {
        $errors[] = 'Target must be a path starting with / or a full URL.';
    }
    if (rtrim($from, '/') === rtrim($to, '/')) {
        $errors[] = 'Source and target cannot be the same.';
    }
    if (!$errors) {
        db_exec('INSERT INTO redirects (source_path, target_path, status_code, is_auto) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE target_path = VALUES(target_path), status_code = VALUES(status_code), is_active = 1', [$from, $to, $code]);
        audit_log('redirect_saved', 'redirect', null, ['from' => $from, 'to' => $to]);
        flash('success', 'Redirect saved.');
        redirect(admin_url('redirects'));
    }
}
$rows = db_all('SELECT * FROM redirects ORDER BY created_at DESC LIMIT 500');
admin_header('Redirects', 'redirects');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="card mb-3"><div class="card-body">
  <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?>
    <div class="col-md-4"><label class="form-label">Old path</label><input class="form-control" name="source_path" placeholder="/old-url" required value="<?= e(input('source_path')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Redirect to</label><input class="form-control" name="target_path" placeholder="/product/new-url" required value="<?= e(input('target_path')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="status_code"><option value="301">301 permanent</option><option value="302">302 temporary</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Add redirect</button></div>
  </form>
  <p class="small text-muted mt-2 mb-0">Redirects are created automatically when you change a product, category, collection or page slug. They apply only to URLs that would otherwise return 404.</p>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Source</th><th>Active</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td><code><?= e($r['source_path']) ?></code></td><td><code><?= e($r['target_path']) ?></code></td><td><?= (int) $r['status_code'] ?></td><td><?= (int) $r['hits'] ?></td><td><small><?= $r['is_auto'] ? 'automatic' : 'manual' ?></small></td>
      <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="redirect" data-field="is_active" data-id="<?= (int) $r['id'] ?>" <?= $r['is_active'] ? 'checked' : '' ?>></div></td>
      <td><form method="post" data-confirm="Delete this redirect?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No redirects.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?php admin_footer();
