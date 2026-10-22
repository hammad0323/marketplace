<?php
require __DIR__ . '/../includes/init.php';
admin_require('seo');
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'add') {
        $from = '/' . ltrim((string) parse_url(input('from_path', '', 'post'), PHP_URL_PATH), '/');
        $to = trim(input('to_path', '', 'post'));
        if ($from === '/' || $to === '') {
            flash('error', 'Both the old path and the destination are required.');
        } else {
            redirect_add($from, preg_match('#^https?://#', $to) ? $to : '/' . ltrim($to, '/'), input_int('status_code') === 302 ? 302 : 301);
            if (preg_match('#^https?://#', $to)) db_exec('UPDATE redirects SET to_path = ? WHERE from_path = ?', [$to, $from]);
            flash('success', 'Redirect saved.');
        }
    } elseif ($a === 'toggle') {
        db_exec('UPDATE redirects SET is_active = 1 - is_active WHERE id = ?', [input_int('id')]);
    } elseif ($a === 'delete') {
        db_exec('DELETE FROM redirects WHERE id = ?', [input_int('id')]);
    }
    audit_log('redirect_' . $a, 'redirect');
    redirect('admin/seo/redirects.php');
}
$rows = db_all('SELECT * FROM redirects ORDER BY id DESC');
admin_header('Redirects');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/seo/')) ?>">Settings</a></li><li class="nav-item"><a class="nav-link active" href="#">Redirects</a></li></ul>
<p class="text-muted">Redirects are created automatically when a product, category, collection or page URL changes. Add your own for old links (e.g. from a previous website).</p>
<div class="card a-card"><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="col-md-4"><input class="form-control" name="from_path" placeholder="Old path, e.g. /old-shirt.html" required></div>
    <div class="col-md-4"><input class="form-control" name="to_path" placeholder="New path or URL, e.g. /product/oxford-button-down-shirt" required></div>
    <div class="col-md-2"><select class="form-select" name="status_code"><option value="301">301 permanent</option><option value="302">302 temporary</option></select></div>
    <div class="col-md-2"><button class="btn btn-dark w-100">Add</button></div></form></div></div>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><code><?= e($r['from_path']) ?></code></td><td><code><?= e($r['to_path']) ?></code></td><td><?= (int) $r['status_code'] ?></td><td><?= (int) $r['hits'] ?></td><td><?= a_status_toggle((bool) $r['is_active']) ?></td>
<td class="text-end text-nowrap"><?= a_post_button(url('admin/seo/redirects.php'), ['action' => 'toggle', 'id' => $r['id']], '<i class="bi bi-power"></i>', 'btn btn-sm btn-light', 'Toggle this redirect?') ?> <?= a_post_button(url('admin/seo/redirects.php'), ['action' => 'delete', 'id' => $r['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete this redirect?') ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-3">No redirects.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_footer();
