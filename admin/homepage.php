<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
$types = ['hero' => 'Hero banner slider', 'categories' => 'Shop by categories', 'products' => 'Product carousel', 'rashan_cta' => 'Monthly Rashan call-to-action',
    'promotions' => 'Special promotions', 'promo_banners' => 'Promotional image banners', 'brands' => 'Shop by brand', 'testimonials' => 'Customer testimonials', 'newsletter' => 'Newsletter signup'];
$sources = ['featured' => 'Featured products', 'best_sellers' => 'Best sellers (auto + flagged)', 'new' => 'New arrivals', 'on_sale' => 'Discounted / weekly deals', 'trending' => 'Trending (most viewed)',
    'rashan' => 'Rashan items', 'category' => 'From a category', 'recent_viewed' => "Customer's recently viewed", 'manual' => 'Only manually selected products'];
if (is_post()) {
    csrf_check();
    $act = input('act'); $id = input_int('id');
    if ($act === 'reorder') { foreach (input_arr('order') as $i => $sid) db_exec('UPDATE homepage_sections SET sort_order = ? WHERE id = ?', [$i + 1, (int) $sid]); json_out(['ok' => true]); }
    if ($act === 'toggle') { db_exec('UPDATE homepage_sections SET is_active = 1 - is_active WHERE id = ?', [$id]); redirect('admin/homepage.php'); }
    if ($act === 'delete') { db_exec('DELETE FROM homepage_sections WHERE id = ?', [$id]); flash('success', 'Section removed.'); redirect('admin/homepage.php'); }
    if ($act === 'add') {
        $t = array_key_exists(input('section_type'), $types) ? input('section_type') : 'products';
        $id = db_insert('INSERT INTO homepage_sections (section_type, title, settings, sort_order) VALUES (?, ?, ?, ?)', [$t, $types[$t], json_encode(['limit' => 10, 'source' => 'featured']), (int) db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM homepage_sections')]);
        redirect('admin/homepage.php?edit=' . $id);
    }
    if ($act === 'save') {
        $s = db_row('SELECT * FROM homepage_sections WHERE id = ?', [$id]);
        $cfg = json_decode((string) $s['settings'], true) ?: [];
        $cfg['limit'] = max(1, min(24, input_int('limit', 10)));
        foreach (['source', 'link'] as $k) $cfg[$k] = input($k);
        $cfg['category_id'] = input_int('category_id');
        $cfg['show_count'] = input_int('show_count');
        $cfg['in_stock'] = input_int('in_stock');
        $cfg['autoplay'] = max(0, input_int('autoplay', 5000));
        db_exec('UPDATE homepage_sections SET title = ?, subtitle = ?, settings = ?, is_active = ? WHERE id = ?', [input('title') ?: null, input('subtitle') ?: null, json_encode($cfg), input_int('is_active'), $id]);
        $itemType = ['products' => 'product', 'categories' => 'category', 'brands' => 'brand'][$s['section_type']] ?? null;
        if ($itemType) {
            db_exec('DELETE FROM homepage_section_items WHERE section_id = ?', [$id]);
            foreach (array_values(array_unique(array_map('intval', input_arr('items')))) as $i => $iid) {
                if ($iid) db_exec('INSERT INTO homepage_section_items (section_id, item_type, item_id, sort_order) VALUES (?, ?, ?, ?)', [$id, $itemType, $iid, $i]);
            }
        }
        flash('success', 'Section saved.');
        redirect('admin/homepage.php');
    }
}
$edit = input_int('edit') ? db_row('SELECT * FROM homepage_sections WHERE id = ?', [input_int('edit')]) : null;
admin_header('Homepage Sections', 'homepage');
if ($edit):
    $cfg = json_decode((string) $edit['settings'], true) ?: [];
    $t = $edit['section_type'];
    $items = db_all('SELECT * FROM homepage_section_items WHERE section_id = ? ORDER BY sort_order', [$edit['id']]);
?>
<form method="post" class="card" style="max-width:900px"><div class="card-header">Edit section: <?= e($types[$t] ?? $t) ?></div><div class="card-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <div class="row"><div class="col-md-6"><?= af_text('title', 'Title', $edit['title']) ?></div><div class="col-md-6"><?= af_text('subtitle', 'Subtitle', $edit['subtitle']) ?></div>
  <?php if (in_array($t, ['products', 'categories', 'brands', 'promotions'], true)): ?><div class="col-md-3"><?= af_text('limit', 'Items to show', $cfg['limit'] ?? 10, ['type' => 'number', 'min' => 1, 'max' => 24]) ?></div><?php endif; ?>
  <?php if ($t === 'products'): ?>
    <div class="col-md-5"><?= af_select('source', 'Products source', $sources, $cfg['source'] ?? 'featured') ?></div>
    <div class="col-md-4"><?php $co = ['' => '—']; foreach (category_options() as $o) $co[$o['id']] = $o['label']; echo af_select('category_id', 'Category (if source = category)', $co, $cfg['category_id'] ?? ''); ?></div>
    <div class="col-md-6"><?= af_text('link', '"View all" link', $cfg['link'] ?? '', ['placeholder' => 'shop/?featured=1']) ?></div>
    <div class="col-md-6 pt-4"><?= af_check('in_stock', 'Only show in-stock products', !empty($cfg['in_stock'])) ?></div>
  <?php elseif ($t === 'categories'): ?><div class="col-md-6 pt-4"><?= af_check('show_count', 'Show product count on cards', !empty($cfg['show_count'])) ?></div>
  <?php elseif ($t === 'hero'): ?><div class="col-md-4"><?= af_text('autoplay', 'Autoplay interval (ms)', $cfg['autoplay'] ?? 5000, ['type' => 'number', 'min' => 0]) ?></div><div class="col-12 small text-muted">Slides are managed in <a href="<?= aurl('banners.php') ?>">Banners</a>.</div>
  <?php elseif ($t === 'promotions'): ?><div class="col-12 small text-muted">Content comes from <a href="<?= aurl('promotions.php') ?>">Promotions</a>.</div>
  <?php elseif ($t === 'promo_banners'): ?><div class="col-12 small text-muted">Uses banners with placement "Promotional banner strip".</div>
  <?php elseif ($t === 'testimonials'): ?><div class="col-12 small text-muted">Manage in <a href="<?= aurl('testimonials.php') ?>">Testimonials</a>.</div><?php endif; ?>
  </div>
  <?php if (in_array($t, ['products', 'categories', 'brands'], true)): ?>
  <hr><label class="form-label fw-semibold">Manually selected <?= $t ?> <small class="text-muted fw-normal">(shown first, in this order<?= $t === 'categories' ? '; if empty, categories marked "show on home" are used' : '' ?>)</small></label>
  <?php if ($t === 'products'): ?>
    <input type="search" class="form-control js-product-picker" data-results="#hsRes" placeholder="Search products to add…"><div class="list-group mt-1" id="hsRes"></div>
  <?php else:
    $opts = $t === 'categories' ? array_map(function ($o) { return ['id' => $o['id'], 'name' => $o['label']]; }, category_options()) : db_all('SELECT id, name FROM brands WHERE deleted_at IS NULL ORDER BY name'); ?>
    <div class="input-group"><select id="hsSel" class="form-select"><?php foreach ($opts as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select><button type="button" class="btn btn-outline-brand" id="hsAdd">Add</button></div>
  <?php endif; ?>
  <ul class="list-group mt-2" id="hsList">
    <?php foreach ($items as $it):
      $label = $it['item_type'] === 'product' ? db_val('SELECT name FROM products WHERE id = ?', [$it['item_id']]) : ($it['item_type'] === 'category' ? (category_get((int) $it['item_id'])['name'] ?? '') : db_val('SELECT name FROM brands WHERE id = ?', [$it['item_id']]));
      if (!$label) continue; ?>
    <li class="list-group-item d-flex align-items-center gap-2"><i class="bi bi-arrow-down-up text-muted"></i><span class="flex-grow-1"><?= e($label) ?></span><input type="hidden" name="items[]" value="<?= (int) $it['item_id'] ?>">
      <button type="button" class="btn btn-sm btn-light js-up">↑</button><button type="button" class="btn btn-sm btn-light js-down">↓</button><button type="button" class="btn btn-sm btn-link text-danger js-rm">remove</button></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <div class="mt-3"><?= af_check('is_active', 'Section enabled', $edit['is_active']) ?></div>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save section</button> <a href="<?= aurl('homepage.php') ?>" class="btn btn-link">Back</a></div></form>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function li(id, label) { return '<li class="list-group-item d-flex align-items-center gap-2"><i class="bi bi-arrow-down-up text-muted"></i><span class="flex-grow-1">' + FSA.esc(label) + '</span><input type="hidden" name="items[]" value="' + id + '"><button type="button" class="btn btn-sm btn-light js-up">↑</button><button type="button" class="btn btn-sm btn-light js-down">↓</button><button type="button" class="btn btn-sm btn-link text-danger js-rm">remove</button></li>'; }
  $(document).on('click', '#hsRes .js-pick', function () { $('#hsList').append(li($(this).data('id'), $(this).data('name'))); $('#hsRes').empty(); $('.js-product-picker').val(''); });
  $('#hsAdd').on('click', function () { var o = $('#hsSel option:selected'); $('#hsList').append(li(o.val(), $.trim(o.text()))); });
  $(document).on('click', '.js-rm', function () { $(this).closest('li').remove(); });
  $(document).on('click', '.js-up', function () { var l = $(this).closest('li'); l.prev().before(l); });
  $(document).on('click', '.js-down', function () { var l = $(this).closest('li'); l.next().after(l); });
});
</script>
<?php else:
    $rows = db_all('SELECT * FROM homepage_sections ORDER BY sort_order, id'); ?>
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
  <p class="text-muted small mb-0">Enable, disable, reorder and configure every homepage section. Use ↑ ↓ to change the order.</p>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="add"><select name="section_type" class="form-select form-select-sm"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-brand text-nowrap">Add section</button></form>
</div>
<ul class="list-group" id="secList">
<?php foreach ($rows as $s): $cfg = json_decode((string) $s['settings'], true) ?: []; ?>
  <li class="list-group-item d-flex align-items-center gap-2 <?= $s['is_active'] ? '' : 'bg-light opacity-75' ?>" data-id="<?= (int) $s['id'] ?>">
    <div class="btn-group-vertical btn-group-sm"><button class="btn btn-light js-mv" data-d="-1" aria-label="Move up">↑</button><button class="btn btn-light js-mv" data-d="1" aria-label="Move down">↓</button></div>
    <div class="flex-grow-1"><strong><?= e($s['title'] ?: $types[$s['section_type']] ?? $s['section_type']) ?></strong> <span class="badge bg-light text-dark border"><?= e($types[$s['section_type']] ?? $s['section_type']) ?></span>
      <?php if ($s['section_type'] === 'products'): ?><span class="badge bg-info-subtle text-info"><?= e($sources[$cfg['source'] ?? 'featured'] ?? '') ?></span><?php endif; ?>
      <?= $s['is_active'] ? '' : '<span class="badge bg-secondary">Disabled</span>' ?></div>
    <a href="?edit=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline-secondary">Configure</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $s['is_active'] ? 'Disable' : 'Enable' ?></button></form>
    <form method="post" data-confirm="Remove this section?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
  </li>
<?php endforeach; ?>
</ul>
<script>
document.addEventListener('DOMContentLoaded', function () {
  $(document).on('click', '.js-mv', function () {
    var li = $(this).closest('li'), d = +$(this).data('d');
    if (d < 0) li.prev().before(li); else li.next().after(li);
    FSA.post('homepage.php', { act: 'reorder', order: $('#secList li').map(function () { return $(this).data('id'); }).get() }).done(function () { FSA.toast('Order saved'); });
  });
});
</script>
<?php endif;
admin_footer();
