<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $act = input('act');
    if ($act === 'delete') {
        $b = db_row('SELECT * FROM banners WHERE id = ?', [$id]);
        if ($b) { foreach (['background_image', 'side_image', 'desktop_image', 'mobile_image'] as $f) upload_delete($b[$f]); db_exec('DELETE FROM banners WHERE id = ?', [$id]); }
        flash('success', 'Banner deleted.'); redirect('admin/banners.php');
    }
    if ($act === 'toggle') { db_exec('UPDATE banners SET is_active = 1 - is_active WHERE id = ?', [$id]); redirect('admin/banners.php'); }
    $old = $id ? db_row('SELECT * FROM banners WHERE id = ?', [$id]) : null;
    $color = function ($v, $d) { return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : $d; };
    $d = ['position' => input('position') === 'promo' ? 'promo' : 'hero', 'heading' => input('heading') ?: null, 'subheading' => input('subheading') ?: null, 'description' => input('description') ?: null,
        'button_text' => input('button_text') ?: null, 'button_url' => input('button_url') ?: null, 'bg_color' => $color(input('bg_color'), '#0f766e'), 'text_color' => $color(input('text_color'), '#ffffff'),
        'starts_at' => input('starts_at') ? date('Y-m-d H:i:s', strtotime(input('starts_at'))) : null, 'ends_at' => input('ends_at') ? date('Y-m-d H:i:s', strtotime(input('ends_at'))) : null,
        'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active')];
    $d['background_image'] = admin_save_image('background_image', 'banners', $old['background_image'] ?? null, 1920);
    $d['side_image'] = admin_save_image('side_image', 'banners', $old['side_image'] ?? null, 800);
    $d['desktop_image'] = admin_save_image('desktop_image', 'banners', $old['desktop_image'] ?? null, 1920);
    $d['mobile_image'] = admin_save_image('mobile_image', 'banners', $old['mobile_image'] ?? null, 900);
    $id ? db_update_row('banners', $d, 'id = ?', [$id]) : db_insert_row('banners', $d);
    flash('success', 'Banner saved.');
    redirect('admin/banners.php');
}
$edit = input_int('edit') ? db_row('SELECT * FROM banners WHERE id = ?', [input_int('edit')]) : null;
admin_header('Banners', 'banners');
if ($edit || input('new')):
    $b = $edit ?: ['id' => 0, 'position' => input('position', 'hero'), 'heading' => '', 'subheading' => '', 'description' => '', 'button_text' => '', 'button_url' => '', 'background_image' => null, 'side_image' => null, 'desktop_image' => null, 'mobile_image' => null, 'bg_color' => '#0f766e', 'text_color' => '#ffffff', 'starts_at' => '', 'ends_at' => '', 'sort_order' => 0, 'is_active' => 1];
?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
<div class="row g-3"><div class="col-lg-8"><div class="card"><div class="card-body"><div class="row">
  <div class="col-md-4"><?= af_select('position', 'Placement', ['hero' => 'Homepage hero slider', 'promo' => 'Promotional banner strip'], $b['position']) ?></div>
  <div class="col-md-8"><?= af_text('heading', 'Heading', $b['heading'], ['maxlength' => 160]) ?></div>
  <div class="col-12"><?= af_text('subheading', 'Subheading', $b['subheading'], ['maxlength' => 200]) ?></div>
  <div class="col-12"><?= af_textarea('description', 'Description', $b['description'], ['maxlength' => 500, 'rows' => 2]) ?></div>
  <div class="col-md-6"><?= af_text('button_text', 'Button text', $b['button_text']) ?></div>
  <div class="col-md-6"><?= af_text('button_url', 'Button link', $b['button_url'], ['placeholder' => 'monthly-rashan/ or https://…']) ?></div>
  <div class="col-md-3"><?= af_text('bg_color', 'Background colour', $b['bg_color'], ['type' => 'color', 'class' => 'form-control form-control-color w-100']) ?></div>
  <div class="col-md-3"><?= af_text('text_color', 'Text colour', $b['text_color'], ['type' => 'color', 'class' => 'form-control form-control-color w-100']) ?></div>
  <div class="col-md-3"><?= af_text('starts_at', 'Start date', $b['starts_at'] ? date('Y-m-d\TH:i', strtotime($b['starts_at'])) : '', ['type' => 'datetime-local']) ?></div>
  <div class="col-md-3"><?= af_text('ends_at', 'End date', $b['ends_at'] ? date('Y-m-d\TH:i', strtotime($b['ends_at'])) : '', ['type' => 'datetime-local']) ?></div>
  <div class="col-md-6"><?= af_image('background_image', 'Background image (optional)', $b['background_image']) ?></div>
  <div class="col-md-6"><?= af_image('side_image', 'Side / product image', $b['side_image']) ?></div>
  <div class="col-12"><hr><p class="small text-muted">Or upload a complete artwork banner (used instead of the text layout):</p></div>
  <div class="col-md-6"><?= af_image('desktop_image', 'Desktop banner (1600×500 recommended)', $b['desktop_image']) ?></div>
  <div class="col-md-6"><?= af_image('mobile_image', 'Mobile banner (800×600 recommended)', $b['mobile_image']) ?></div>
</div></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body"><?= af_text('sort_order', 'Display order', $b['sort_order'], ['type' => 'number']) ?><?= af_check('is_active', 'Active', $b['is_active']) ?>
  <button class="btn btn-brand w-100 mt-2">Save banner</button><a href="<?= aurl('banners.php') ?>" class="btn btn-link w-100">Cancel</a></div></div>
  <div class="card mt-3"><div class="card-header">Live preview</div><div class="card-body p-2"><div id="bPrev" style="border-radius:12px;padding:18px;background:<?= e($b['bg_color']) ?>;color:<?= e($b['text_color']) ?>"><small id="pSub"><?= e($b['subheading']) ?></small><div class="fw-bold fs-5" id="pHead"><?= e($b['heading'] ?: 'Heading') ?></div><small id="pDesc"><?= e($b['description']) ?></small></div></div></div></div>
</div></form>
<script>document.addEventListener('DOMContentLoaded',function(){ $('#f_bg_color,#f_text_color,#f_heading,#f_subheading,#f_description').on('input',function(){ $('#bPrev').css({background:$('#f_bg_color').val(),color:$('#f_text_color').val()}); $('#pHead').text($('#f_heading').val()||'Heading'); $('#pSub').text($('#f_subheading').val()); $('#pDesc').text($('#f_description').val()); }); });</script>
<?php else:
    $rows = db_all('SELECT * FROM banners ORDER BY position, sort_order, id'); ?>
<div class="d-flex justify-content-end gap-2 mb-3"><a href="?new=1&position=hero" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>Hero slide</a><a href="?new=1&position=promo" class="btn btn-outline-brand"><i class="bi bi-plus-lg me-1"></i>Promo banner</a></div>
<div class="row g-3">
<?php foreach ($rows as $b): $live = $b['is_active'] && (!$b['starts_at'] || strtotime($b['starts_at']) <= time()) && (!$b['ends_at'] || strtotime($b['ends_at']) >= time()); ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100">
    <div style="background:<?= e($b['bg_color']) ?>;color:<?= e($b['text_color']) ?>;border-radius:12px 12px 0 0;padding:16px;min-height:120px" class="d-flex justify-content-between">
      <?php if ($b['desktop_image']): ?><img src="<?= e(upload_url($b['desktop_image'])) ?>" class="w-100" style="max-height:110px;object-fit:cover" alt=""><?php else: ?>
      <div><small><?= e($b['subheading']) ?></small><div class="fw-bold"><?= e($b['heading']) ?></div></div><?php if ($b['side_image']): ?><img src="<?= e(upload_url($b['side_image'])) ?>" style="height:80px" alt=""><?php endif; endif; ?>
    </div>
    <div class="card-body small"><span class="badge bg-light text-dark border"><?= $b['position'] === 'hero' ? 'Hero slider' : 'Promo strip' ?></span> <?= $live ? '<span class="badge bg-success">Live</span>' : '<span class="badge bg-secondary">Not live</span>' ?> · order <?= (int) $b['sort_order'] ?>
      <div class="text-muted mt-1"><?= $b['starts_at'] ? fmt_date($b['starts_at']) : 'Always' ?> – <?= $b['ends_at'] ? fmt_date($b['ends_at']) : 'no end' ?></div></div>
    <div class="card-footer bg-white d-flex gap-1"><a href="?edit=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-outline-warning"><?= $b['is_active'] ? 'Disable' : 'Enable' ?></button></form>
      <form method="post" data-confirm="Delete banner?" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div>
  </div></div>
<?php endforeach; ?>
</div>
<?php endif;
admin_footer();
