<?php
/** /manage/pages — static pages (legal pages, About override, custom pages). */
require_admin('pages');
if (is_post()) {
    require_post_csrf();
    $slug = post('slug');
    if (post('action') === 'delete' && page_slug_ok($slug) && is_file(storage_path('pages/' . $slug . '.json'))) {
        $old = page_doc($slug);
        storage_delete('pages/' . $slug . '.json');
        log_me('page.delete', 'page', null, array('slug' => $slug, 'title' => $old['title']), null);
        flash('success', 'Page deleted' . (is_file(__DIR__ . '/../../content/pages/' . $slug . '.html') ? ' — the original file version is shown again.' : '.'));
    }
    bo_redirect('admin', 'pages');
}
$docs = page_docs();
bo_start('admin', 'Pages');
?>
<div class="d-flex mb-3"><p class="text-muted mb-0">A published page named <code>about</code>, <code>support</code> or <code>solution-mosque</code> replaces the built-in page with that address.</p><a class="btn btn-primary ms-auto" href="<?= e(bo_url('admin', 'page-edit')) ?>"><i class="bi bi-plus-lg me-1"></i>New page</a></div>
<div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Title</th><th>Address</th><th>Status</th><th>Footer</th><th>Updated</th><th></th></tr></thead>
  <tbody>
  <?php if (!$docs): ?><tr><td colspan="6" class="text-muted p-3">No pages yet.</td></tr><?php endif; ?>
  <?php foreach ($docs as $slug => $d): ?>
    <tr>
      <td><a class="fw-semibold" href="<?= e(bo_url('admin', 'page-edit?slug=' . $slug)) ?>"><?= e($d['title']) ?></a><?= !empty($d['source']) ? '<div class="small text-muted">from content/pages/' . e($slug) . '.html</div>' : '' ?></td>
      <td class="small"><code>/<?= e($slug) ?></code></td>
      <td><?= status_badge($d['status'] === 'published', 'Published', 'Draft') ?></td>
      <td><?= !empty($d['in_footer']) ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
      <td class="small text-muted"><?= e(fmt_date($d['updated_at'])) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'page-edit?slug=' . $slug)) ?>"><i class="bi bi-pencil"></i></a>
        <?php if ($d['status'] === 'published'): ?><a class="btn btn-sm btn-light" href="<?= e(url('/' . $slug)) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
        <?php if (empty($d['source'])): ?><button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete', 'slug' => $slug))) ?>' data-confirm-text="Delete this page?"><i class="bi bi-trash"></i></button><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php bo_end();
