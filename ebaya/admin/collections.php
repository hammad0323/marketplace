<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('collections.manage');
$errors = [];
$editId = (int)get('edit');

if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'reorder') {
        foreach (array_values((array)($_POST['ids'] ?? [])) as $i => $cid) db_exec('UPDATE collections SET sort_order = ? WHERE id = ?', [$i, (int)$cid]);
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        $cid = (int)post('id');
        db_exec('DELETE FROM collections WHERE id = ?', [$cid]);
        audit('collection_delete', 'collection', $cid);
        flash('success', 'Collection deleted.');
        redirect(admin_url('collections'));
    }
    if ($act === 'save') {
        $cid = (int)post('id');
        $old = $cid ? db_one('SELECT * FROM collections WHERE id = ?', [$cid]) : null;
        $d = [
            'name' => mb_substr(post('name'), 0, 150), 'slug' => slugify(post('slug') ?: post('name')),
            'description' => mb_substr(post('description'), 0, 3000), 'link_url' => clean_url(post('link_url')) ?: null,
            'seo_title' => mb_substr(post('seo_title'), 0, 190) ?: null, 'meta_description' => mb_substr(post('meta_description'), 0, 320) ?: null,
            'show_on_home' => post('show_on_home') ? 1 : 0, 'noindex' => post('noindex') ? 1 : 0,
            'status' => post('status') === 'inactive' ? 'inactive' : 'active', 'sort_order' => (int)post('sort_order'),
        ];
        if (!v_len($d['name'], 2, 150)) $errors[] = 'Name is required.';
        if (db_val('SELECT id FROM collections WHERE slug = ? AND id <> ?', [$d['slug'], $cid])) $errors[] = 'Slug already in use.';
        if (!$errors) {
            try {
                $d['image'] = f_image_value('image', $old['image'] ?? null, 'collections');
                $d['banner_image'] = f_image_value('banner_image', $old['banner_image'] ?? null, 'collections');
                db_tx(function () use (&$cid, $d, $old) {
                    if ($cid) {
                        db_exec('UPDATE collections SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', array_merge(array_values($d), [$cid]));
                        if ($old['slug'] !== $d['slug']) redirect_add_auto('/collections/' . $old['slug'], '/collections/' . $d['slug']);
                    } else {
                        $cid = db_insert('INSERT INTO collections (' . implode(',', array_keys($d)) . ') VALUES (' . db_in($d) . ')', array_values($d));
                    }
                    db_exec('DELETE FROM collection_products WHERE collection_id = ?', [$cid]);
                    foreach (array_values(array_unique(array_map('intval', (array)($_POST['products'] ?? [])))) as $i => $pid) {
                        if ($pid) db_exec('INSERT INTO collection_products (collection_id, product_id, sort_order) VALUES (?, ?, ?)', [$cid, $pid, $i]);
                    }
                });
                audit($old ? 'collection_update' : 'collection_create', 'collection', $cid, ['name' => $d['name']]);
                flash('success', 'Collection saved.');
                redirect(admin_url('collections?edit=' . $cid));
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
        $editId = $cid ?: -1;
    }
}
$cols = db_all('SELECT c.*, (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.id) n FROM collections c ORDER BY sort_order, id');
$edit = $editId > 0 ? db_one('SELECT * FROM collections WHERE id = ?', [$editId]) : (($editId === -1 || get('new')) ? ($_POST ?: []) : null);
$selected = !empty($edit['id']) ? array_map('intval', db_col('SELECT product_id FROM collection_products WHERE collection_id = ? ORDER BY sort_order', [(int)$edit['id']])) : [];
$allProducts = [];
foreach (db_all('SELECT id, name, status FROM products ORDER BY name') as $r) $allProducts[$r['id']] = $r['name'] . ($r['status'] !== 'published' ? ' [' . $r['status'] . ']' : '');

$admin_title = 'Collections';
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-6">
    <div class="d-flex mb-2"><span class="small text-muted">Collections power “Shop by Craft or Detail”. Drag to reorder.</span><a href="?new=1" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add collection</a></div>
    <div data-sortable="<?= e(admin_url('collections')) ?>">
      <?php foreach ($cols as $c): ?>
        <div class="section-row" data-id="<?= (int)$c['id'] ?>"><i class="bi bi-grip-vertical drag-handle"></i><img src="<?= e(img_url($c['image'])) ?>" class="thumb" alt="">
          <div class="flex-grow-1"><strong><?= e($c['name']) ?></strong> <?= status_badge($c['status']) ?> <?= $c['show_on_home'] ? '<span class="badge text-bg-light">Homepage</span>' : '' ?><div class="small text-muted">/collections/<?= e($c['slug']) ?> · <?= (int)$c['n'] ?> products</div></div>
          <a class="btn btn-sm btn-light" href="<?= e(url('collections/' . $c['slug'])) ?>" target="_blank"><i class="bi bi-eye"></i></a>
          <a class="btn btn-sm btn-light" href="?edit=<?= (int)$c['id'] ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" data-confirm="Delete this collection?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-xl-6">
    <?php if ($edit !== null): $ev = fn($k, $d = '') => $edit[$k] ?? $d; ?>
    <div class="card"><div class="card-header"><?= !empty($edit['id']) ? 'Edit collection' : 'New collection' ?></div><div class="card-body">
      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$ev('id', 0) ?>">
        <?= f_text('name', 'Title', $ev('name'), ['required' => true]) ?>
        <?= f_text('slug', 'URL slug', $ev('slug'), ['help' => '/collections/slug']) ?>
        <?= f_text('description', 'Description', $ev('description'), ['type' => 'textarea', 'rows' => 3]) ?>
        <?= f_text('link_url', 'Custom link (optional)', $ev('link_url'), ['help' => 'Leave blank to link to the collection page.']) ?>
        <?= f_image('image', 'Card image', $ev('image') ?: null) ?>
        <?= f_image('banner_image', 'Banner image (optional)', $ev('banner_image') ?: null) ?>
        <?= f_select('products', 'Products (in display order of selection)', $allProducts, $selected, ['multiple' => true, 'size' => 10]) ?>
        <div class="row"><div class="col-6"><?= f_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], $ev('status', 'active')) ?></div><div class="col-6"><?= f_text('sort_order', 'Sort order', $ev('sort_order', 0), ['type' => 'number']) ?></div></div>
        <?= f_toggle('show_on_home', 'Show on homepage', $ev('show_on_home', 1)) ?>
        <?= f_text('seo_title', 'SEO title', $ev('seo_title')) ?>
        <?= f_text('meta_description', 'Meta description', $ev('meta_description'), ['type' => 'textarea', 'rows' => 2]) ?>
        <?= f_toggle('noindex', 'Hide from search engines', $ev('noindex')) ?>
        <button class="btn btn-primary">Save collection</button> <a href="<?= e(admin_url('collections')) ?>" class="btn btn-light">Cancel</a>
      </form>
    </div></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
