<?php
require __DIR__ . '/../includes/init.php';
admin_require('homepage');
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'order') {
        foreach ((array) ($_POST['order'] ?? []) as $sid => $o) db_exec('UPDATE banner_slides SET sort_order = ? WHERE id = ?', [(int) $o, (int) $sid]);
    } elseif ($a === 'toggle') {
        db_exec('UPDATE banner_slides SET is_active = 1 - is_active WHERE id = ?', [input_int('id')]);
    } elseif ($a === 'delete') {
        $s = db_row('SELECT * FROM banner_slides WHERE id = ?', [input_int('id')]);
        if ($s) { delete_upload($s['image_desktop']); delete_upload($s['image_mobile']); db_exec('DELETE FROM banner_slides WHERE id = ?', [(int) $s['id']]); }
    }
    audit_log('slides_' . $a, 'banner_slide', input_int('id') ?: null);
    flash('success', 'Saved.');
    redirect('admin/homepage/slides.php');
}
$slides = db_all('SELECT * FROM banner_slides ORDER BY sort_order, id');
admin_header('Hero slides');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Carousel autoplay, transition and arrows are set on the <a href="<?= e(url('admin/homepage/')) ?>">Hero section</a>. Inactive slides appear in homepage preview only.</p>
<a class="btn btn-dark" href="<?= e(url('admin/homepage/slide.php')) ?>"><i class="bi bi-plus-lg"></i> Add slide</a></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="order">
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Image</th><th>Heading</th><th>Buttons</th><th>Status</th><th style="width:90px">Order</th><th></th></tr></thead><tbody>
<?php foreach ($slides as $s): ?><tr><td><div class="a-thumb" style="width:120px;height:68px"><?php if ($s['image_desktop']): ?><img src="<?= e(img_sized($s['image_desktop'], 300)) ?>" alt=""><?php endif; ?></div></td>
<td><strong><?= e($s['heading']) ?></strong><div class="small text-muted"><?= e($s['eyebrow']) ?></div></td><td class="small"><?= e($s['btn1_text']) ?><?= $s['btn2_text'] ? ' · ' . e($s['btn2_text']) : '' ?></td>
<td><?= a_status_toggle((bool) $s['is_active']) ?></td><td><input class="form-control form-control-sm" type="number" name="order[<?= (int) $s['id'] ?>]" value="<?= (int) $s['sort_order'] ?>"></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(url('admin/homepage/slide.php?id=' . $s['id'])) ?>"><i class="bi bi-pencil"></i></a>
<button class="btn btn-sm btn-light" form="tg<?= (int) $s['id'] ?>"><i class="bi bi-power"></i></button><button class="btn btn-sm btn-light text-danger" form="dl<?= (int) $s['id'] ?>"><i class="bi bi-trash"></i></button></td></tr><?php endforeach; ?>
</tbody></table></div></div><button class="btn btn-dark">Save order</button></form>
<?php foreach ($slides as $s): ?><form id="tg<?= (int) $s['id'] ?>" method="post" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form>
<form id="dl<?= (int) $s['id'] ?>" method="post" class="d-none" data-confirm="Delete this slide?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form><?php endforeach; ?>
<?php admin_footer();
