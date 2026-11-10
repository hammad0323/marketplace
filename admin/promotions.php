<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') { $p = db_row('SELECT image FROM promotions WHERE id = ?', [$id]); upload_delete($p['image'] ?? null); db_exec('DELETE FROM promotions WHERE id = ?', [$id]); flash('success', 'Promotion deleted.'); redirect('admin/promotions.php'); }
    $old = $id ? db_row('SELECT * FROM promotions WHERE id = ?', [$id]) : null;
    if (input('title') === '') { flash('danger', 'Title is required.'); redirect('admin/promotions.php?new=1'); }
    $color = function ($v, $d) { return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : $d; };
    $d = ['title' => mb_substr(input('title'), 0, 160), 'slug' => unique_slug('promotions', input('slug') ?: input('title'), $id), 'promo_type' => array_key_exists(input('promo_type'), promo_types()) ? input('promo_type') : 'seasonal',
        'subtitle' => input('subtitle') ?: null, 'description' => input('description') ?: null, 'badge_text' => input('badge_text') ?: null, 'bg_color' => $color(input('bg_color'), '#0f766e'), 'text_color' => $color(input('text_color'), '#ffffff'),
        'button_text' => input('button_text') ?: null, 'link_url' => input('link_url') ?: null, 'starts_at' => input('starts_at') ? date('Y-m-d H:i:s', strtotime(input('starts_at'))) : null,
        'ends_at' => input('ends_at') ? date('Y-m-d H:i:s', strtotime(input('ends_at'))) : null, 'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active')];
    $d['image'] = admin_save_image('image', 'promotions', $old['image'] ?? null, 900);
    if ($id) { db_update_row('promotions', $d, 'id = ?', [$id]); if ($old['slug'] !== $d['slug']) seo_add_redirect('/offers/' . $old['slug'] . '/', '/offers/' . $d['slug'] . '/'); }
    else $id = db_insert_row('promotions', $d);
    db_exec('DELETE FROM promotion_products WHERE promotion_id = ?', [$id]);
    foreach (array_values(array_unique(array_map('intval', input_arr('products')))) as $i => $pid) if ($pid) db_exec('INSERT INTO promotion_products (promotion_id, product_id, sort_order) VALUES (?, ?, ?)', [$id, $pid, $i]);
    seo_save('promotion', $id, input_arr('seo'));
    flash('success', 'Promotion saved.');
    redirect('admin/promotions.php');
}
$edit = input_int('edit') ? db_row('SELECT * FROM promotions WHERE id = ?', [input_int('edit')]) : null;
admin_header('Promotions', 'promotions');
if ($edit || input('new')):
    $p = $edit ?: ['id' => 0, 'title' => '', 'slug' => '', 'promo_type' => 'seasonal', 'subtitle' => '', 'description' => '', 'badge_text' => '', 'image' => null, 'bg_color' => '#0f766e', 'text_color' => '#ffffff', 'button_text' => 'Shop now', 'link_url' => '', 'starts_at' => '', 'ends_at' => '', 'sort_order' => 0, 'is_active' => 1];
    $prods = $p['id'] ? db_all('SELECT p.id, p.name FROM promotion_products pp JOIN products p ON p.id = pp.product_id WHERE pp.promotion_id = ? ORDER BY pp.sort_order', [$p['id']]) : [];
?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<div class="row g-3"><div class="col-lg-8">
  <div class="card mb-3"><div class="card-body"><div class="row">
    <div class="col-md-8"><?= af_text('title', 'Title *', $p['title'], ['required' => true, 'class' => 'form-control js-slug-source', 'data-slug' => '#f_slug']) ?></div>
    <div class="col-md-4"><?= af_select('promo_type', 'Type', promo_types(), $p['promo_type']) ?></div>
    <div class="col-md-6"><?= af_text('slug', 'URL slug (/offers/…)', $p['slug'], ['class' => 'form-control js-slug']) ?></div>
    <div class="col-md-6"><?= af_text('badge_text', 'Badge text', $p['badge_text'], ['placeholder' => 'UP TO 20% OFF']) ?></div>
    <div class="col-12"><?= af_text('subtitle', 'Subtitle', $p['subtitle']) ?></div>
    <div class="col-12"><?= af_textarea('description', 'Description (offer page)', $p['description'], ['rows' => 4]) ?></div>
    <div class="col-md-3"><?= af_text('bg_color', 'Card colour', $p['bg_color'], ['type' => 'color', 'class' => 'form-control form-control-color w-100']) ?></div>
    <div class="col-md-3"><?= af_text('text_color', 'Text colour', $p['text_color'], ['type' => 'color', 'class' => 'form-control form-control-color w-100']) ?></div>
    <div class="col-md-3"><?= af_text('button_text', 'Button text', $p['button_text']) ?></div>
    <div class="col-md-3"><?= af_text('link_url', 'Custom link (optional)', $p['link_url'], ['placeholder' => 'Default: offer page']) ?></div>
    <div class="col-12"><?= af_image('image', 'Image', $p['image']) ?></div>
  </div></div></div>
  <div class="card mb-3"><div class="card-header">Products in this promotion</div><div class="card-body">
    <input type="search" class="form-control js-product-picker" data-results="#prRes" placeholder="Search products to add…"><div class="list-group mt-1" id="prRes"></div>
    <ul class="list-group mt-2" id="prList"><?php foreach ($prods as $x): ?><li class="list-group-item d-flex justify-content-between small"><?= e($x['name']) ?><input type="hidden" name="products[]" value="<?= (int) $x['id'] ?>"><button type="button" class="btn btn-sm btn-link text-danger p-0 js-rm">remove</button></li><?php endforeach; ?></ul>
    <p class="small text-muted mt-2 mb-0">Tip: set sale prices on these products to show the discount.</p>
  </div></div>
  <div class="card"><div class="card-header">SEO</div><div class="card-body"><?= af_seo_fields(seo_get('promotion', (int) $p['id']), 'offers/', $p['slug']) ?></div></div>
</div><div class="col-lg-4"><div class="card"><div class="card-body">
  <?= af_text('starts_at', 'Starts', $p['starts_at'] ? date('Y-m-d\TH:i', strtotime($p['starts_at'])) : '', ['type' => 'datetime-local']) ?><?= af_text('ends_at', 'Ends', $p['ends_at'] ? date('Y-m-d\TH:i', strtotime($p['ends_at'])) : '', ['type' => 'datetime-local']) ?>
  <?= af_text('sort_order', 'Order', $p['sort_order'], ['type' => 'number']) ?><?= af_check('is_active', 'Active', $p['is_active']) ?>
  <button class="btn btn-brand w-100 mt-2">Save promotion</button><a class="btn btn-link w-100" href="<?= aurl('promotions.php') ?>">Cancel</a>
</div></div></div></div></form>
<script>document.addEventListener('DOMContentLoaded', function () {
  $(document).on('click', '#prRes .js-pick', function () { var id = $(this).data('id'); if (!$('#prList input[value="' + id + '"]').length) $('#prList').append('<li class="list-group-item d-flex justify-content-between small">' + FSA.esc($(this).data('name')) + '<input type="hidden" name="products[]" value="' + id + '"><button type="button" class="btn btn-sm btn-link text-danger p-0 js-rm">remove</button></li>'); $('#prRes').empty(); $('.js-product-picker').val(''); });
  $(document).on('click', '.js-rm', function () { $(this).closest('li').remove(); });
});</script>
<?php else:
    $rows = db_all('SELECT p.*, (SELECT COUNT(*) FROM promotion_products WHERE promotion_id = p.id) AS cnt FROM promotions p ORDER BY sort_order, id'); ?>
<div class="d-flex justify-content-end mb-3"><a href="?new=1" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New promotion</a></div>
<div class="row g-3"><?php foreach ($rows as $p): $live = $p['is_active'] && (!$p['starts_at'] || strtotime($p['starts_at']) <= time()) && (!$p['ends_at'] || strtotime($p['ends_at']) >= time()); ?>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div style="background:<?= e($p['bg_color']) ?>;color:<?= e($p['text_color']) ?>;border-radius:12px 12px 0 0;padding:16px;min-height:120px">
    <small><?= e(promo_types()[$p['promo_type']] ?? '') ?></small><div class="fw-bold fs-5"><?= e($p['title']) ?></div><small><?= e($p['subtitle']) ?></small></div>
    <div class="card-body small"><?= $live ? '<span class="badge bg-success">Live</span>' : '<span class="badge bg-secondary">Not live</span>' ?> · <?= (int) $p['cnt'] ?> products<br><span class="text-muted"><?= $p['ends_at'] ? 'Ends ' . fmt_date($p['ends_at']) : 'No end date' ?></span></div>
    <div class="card-footer bg-white d-flex gap-1"><a href="?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a><a href="<?= e(promotion_url($p)) ?>" target="_blank" class="btn btn-sm btn-light">View</a>
      <form method="post" class="ms-auto" data-confirm="Delete promotion?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></div></div>
<?php endforeach; ?></div>
<?php endif;
admin_footer();
