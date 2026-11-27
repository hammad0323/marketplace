<?php
/** /manage/blogs — all blogs: existing rows + drafts in storage. */
require_admin('blogs');
blogs_publish_due();
if (is_post()) {
    require_post_csrf();
    $key = post('key');
    $action = post('action');
    if ($action === 'duplicate' && blog_doc_key_ok($key)) {
        $doc = blog_doc($key);
        if (!$doc && ctype_digit($key)) {
            $b = get_blog((int)$key);
            $doc = $b ? blog_doc_defaults($b) : null;
        }
        if ($doc) {
            $new = 'new-' . bin2hex(random_bytes(6));
            $doc['blog_id'] = null;
            $doc['status'] = 'draft';
            $doc['publish_at'] = '';
            $doc['heading'] = 'Copy of ' . $doc['heading'];
            $doc['slug'] = blog_slugify($doc['heading']);
            $doc['blocks'] = array_values(array_filter((array)$doc['blocks'], function ($bl) { return $bl['type'] !== 'legacy_guide'; }));
            blog_doc_save($new, $doc);
            log_me('blog.duplicate', 'blog', ctype_digit($key) ? (int)$key : null, null, array('from' => $key, 'to' => $new));
            flash('success', 'Duplicated as a new draft.');
            bo_redirect('admin', 'blog-edit?key=' . $new);
        }
    } elseif ($action === 'delete_draft' && strpos($key, 'new-') === 0 && blog_doc($key)) {
        storage_delete('blogs/' . $key . '.json');
        log_me('blog.delete_draft', 'blog', null, array('key' => $key), null);
        flash('success', 'Draft deleted.');
    }
    bo_redirect('admin', 'blogs');
}
$rows = array();
foreach (get_blogs(0, true) as $b) {
    $doc = blog_doc((string)$b['id']);
    $rows[] = array('key' => (string)$b['id'], 'heading' => $doc ? $doc['heading'] : $b['heading'], 'status' => $doc ? $doc['status'] : 'published', 'publish_at' => $doc ? $doc['publish_at'] : '',
        'updated' => $doc && !empty($doc['updated_at']) ? $doc['updated_at'] : $b['updated_date'], 'author' => $b['author'], 'city' => $b['city_title'], 'builder' => (bool)$doc, 'row' => $b);
}
foreach (storage_list('blogs') as $k) {
    if (strpos($k, 'new-') === 0) {
        $doc = blog_doc($k);
        if ($doc) {
            $c = !empty($doc['city_id']) ? get_city((int)$doc['city_id']) : null;
            array_unshift($rows, array('key' => $k, 'heading' => $doc['heading'] !== '' ? $doc['heading'] : '(untitled)', 'status' => $doc['status'], 'publish_at' => $doc['publish_at'],
                'updated' => $doc['updated_at'], 'author' => $doc['author'], 'city' => $c ? $c['city_title'] : '', 'builder' => true, 'row' => null));
        }
    }
}
$badge = array('published' => 'text-bg-success', 'draft' => 'text-bg-secondary', 'scheduled' => 'text-bg-info');
bo_start('admin', 'Blogs');
?>
<div class="d-flex mb-3"><p class="text-muted mb-0">Existing city guides keep their original content as a block — open one to add sections around it.</p><a class="btn btn-primary ms-auto" href="<?= e(bo_url('admin', 'blog-edit')) ?>"><i class="bi bi-plus-lg me-1"></i>New blog</a></div>
<div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Title</th><th>Status</th><th>City</th><th>Updated</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a class="fw-semibold" href="<?= e(bo_url('admin', 'blog-edit?key=' . rawurlencode($r['key']))) ?>"><?= e($r['heading']) ?></a><div class="small text-muted"><?= e($r['author']) ?><?= $r['builder'] ? ' · <i class="bi bi-grid-1x2"></i> builder' : ' · original layout' ?></div></td>
      <td><span class="badge <?= $badge[$r['status']] ?? 'text-bg-light' ?>"><?= e(ucfirst($r['status'])) ?></span><?= $r['status'] === 'scheduled' ? '<div class="small text-muted">' . e($r['publish_at']) . '</div>' : '' ?><?= strpos($r['key'], 'new-') === 0 ? '<div class="small text-muted">not yet on the site</div>' : '' ?></td>
      <td class="small"><?= e($r['city']) ?></td>
      <td class="small text-muted text-nowrap"><?= e(fmt_date($r['updated'])) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'blog-edit?key=' . rawurlencode($r['key']))) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
        <?php if ($r['row'] && $r['status'] === 'published'): ?><a class="btn btn-sm btn-light" href="<?= e(url(blog_url($r['row']))) ?>" target="_blank" rel="noopener" title="View"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
        <button class="btn btn-sm btn-light" type="button" data-post-action='<?= e(json_encode(array('action' => 'duplicate', 'key' => $r['key']))) ?>' title="Duplicate"><i class="bi bi-copy"></i></button>
        <?php if (strpos($r['key'], 'new-') === 0): ?><button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete_draft', 'key' => $r['key']))) ?>' data-confirm-text="Delete this draft?" title="Delete draft"><i class="bi bi-trash"></i></button><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php bo_end();
