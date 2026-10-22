<?php
require __DIR__ . '/../includes/init.php';
admin_require('content');
if (is_post() && input('action', '', 'post') === 'delete') {
    $p = db_row('SELECT * FROM pages WHERE id = ?', [input_int('id')]);
    if ($p) {
        db_exec('DELETE FROM pages WHERE id = ?', [(int) $p['id']]);
        audit_log('page_deleted', 'page', (int) $p['id'], ['slug' => $p['slug']]);
        flash('success', 'Page deleted.');
    }
    redirect('admin/content/');
}
$pages = db_all('SELECT * FROM pages ORDER BY title');
admin_header('Content pages');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">About, contact, size guide, policies, FAQs and other pages. Link them from <a href="<?= e(url('admin/settings/navigation.php?menu=footer_help')) ?>">Navigation</a>.</p>
<a class="btn btn-dark" href="<?= e(url('admin/content/edit.php')) ?>"><i class="bi bi-plus-lg"></i> Add page</a></div>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Title</th><th>URL</th><th>Template</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($pages as $p): ?><tr><td><a href="<?= e(url('admin/content/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a></td><td class="small">/<?= e($p['slug']) ?></td><td><?= e($p['template']) ?></td>
<td><?= a_badge(ucfirst($p['status']), $p['status'] === 'published' ? 'success' : 'secondary') ?></td><td class="small"><?= e(format_date($p['updated_at'] ?? $p['created_at'])) ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(url($p['slug'])) ?>" target="_blank"><i class="bi bi-eye"></i></a> <a class="btn btn-sm btn-light" href="<?= e(url('admin/content/edit.php?id=' . $p['id'])) ?>"><i class="bi bi-pencil"></i></a>
<?= a_post_button(url('admin/content/'), ['action' => 'delete', 'id' => $p['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete page ' . $p['title'] . '?') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php admin_footer();
