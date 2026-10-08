<?php
require __DIR__ . '/includes/admin.php';
require_section('pages');

$id = (int)get('id');
$editing = $id || get('new') === '1';
$pg = $id ? row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
$d = $pg ?: ['title' => '', 'slug' => '', 'content' => '', 'meta_title' => '', 'meta_description' => '', 'show_footer' => 1, 'status' => 1, 'sort_order' => 0];

if (is_post()) {
    require_csrf();
    if (post('do') === 'delete') {
        q('DELETE FROM pages WHERE id = ?', [(int)post('id')]);
        flash('success', 'Page deleted.');
        redirect('admin/pages');
    }
    foreach (['title', 'slug', 'content', 'meta_title', 'meta_description', 'sort_order'] as $k) $d[$k] = trim((string)post($k));
    $d['show_footer'] = post('show_footer') === '1' ? 1 : 0;
    $d['status'] = post('status') === '1' ? 1 : 0;
    if ($d['title'] === '') {
        flash('error', 'Title is required.');
    } else {
        $slug = unique_slug('pages', $d['slug'] ?: $d['title'], $id);
        $vals = [$d['title'], $slug, $d['content'], $d['meta_title'], $d['meta_description'], $d['show_footer'], $d['status'], (int)$d['sort_order']];
        $set = 'title=?, slug=?, content=?, meta_title=?, meta_description=?, show_footer=?, status=?, sort_order=?, updated_at=NOW()';
        if ($id) q("UPDATE pages SET $set WHERE id = ?", array_merge($vals, [$id]));
        else { q("INSERT INTO pages SET $set", $vals); $id = (int)db()->lastInsertId(); }
        flash('success', 'Page saved.');
        redirect('admin/pages?id=' . $id);
    }
}

admin_header($editing ? ($id ? 'Edit Page' : 'New Page') : 'Pages', 'pages');
if ($editing): ?>
  <p><a class="link" href="<?= url('admin/pages') ?>">← All pages</a></p>
  <form method="post" class="edit-layout">
    <?= csrf_field() ?>
    <div class="edit-main">
      <div class="card">
        <?= f_text('title', 'Title *', $d['title'], ['attrs' => 'required data-slug-source']) ?>
        <?= f_text('slug', 'URL slug', $d['slug'], ['attrs' => 'data-slug-target', 'help' => e(site_url()) . '/page/<b data-slug-preview>' . e($d['slug']) . '</b>']) ?>
        <label class="field"><span>Content</span><textarea name="content" rows="16" data-editor><?= e($d['content']) ?></textarea></label>
      </div>
      <div class="card">
        <div class="card__head"><h3>SEO</h3></div>
        <?= f_text('meta_title', 'Meta title', $d['meta_title'], ['attrs' => 'data-count="60"']) ?>
        <?= f_text('meta_description', 'Meta description', $d['meta_description'], ['type' => 'textarea', 'rows' => 3, 'attrs' => 'data-count="160"']) ?>
      </div>
    </div>
    <aside class="edit-side"><div class="card sticky">
      <?= f_switch('status', 'Published', $d['status']) ?>
      <?= f_switch('show_footer', 'Link in footer', $d['show_footer']) ?>
      <?= f_text('sort_order', 'Footer order', $d['sort_order'], ['type' => 'number']) ?>
      <button class="btn btn-primary btn-block">Save Page</button>
      <?php if ($id): ?><a class="btn btn-block" target="_blank" href="<?= url('page/' . $d['slug']) ?>">View page ↗</a><?php endif; ?>
    </div></aside>
  </form>
<?php else:
  $pages = rows('SELECT * FROM pages ORDER BY sort_order, title'); ?>
  <div class="toolbar"><span></span><a class="btn btn-primary" href="?new=1"><?= aicon('plus') ?> New Page</a></div>
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Title</th><th>URL</th><th>Footer</th><th>Status</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
      <tr><td><a href="?id=<?= $p['id'] ?>"><strong><?= e($p['title']) ?></strong></a></td><td class="muted">/page/<?= e($p['slug']) ?></td><td><?= $p['show_footer'] ? '✓' : '—' ?></td>
        <td><?= $p['status'] ? '<span class="badge badge-delivered">Published</span>' : '<span class="badge">Draft</span>' ?></td><td class="muted"><?= $p['updated_at'] ? date('d M Y', strtotime($p['updated_at'])) : '—' ?></td>
        <td class="actions"><a class="icon" href="?id=<?= $p['id'] ?>"><?= aicon('edit') ?></a><a class="icon" target="_blank" href="<?= url('page/' . $p['slug']) ?>"><?= aicon('eye') ?></a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="icon danger" name="id" value="<?= $p['id'] ?>" data-confirm="Delete this page?"><?= aicon('trash') ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
<?php endif;
admin_footer();
