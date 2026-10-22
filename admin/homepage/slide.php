<?php
require __DIR__ . '/../includes/init.php';
admin_require('homepage');
$id = input_int('id');
$slide = $id ? db_row('SELECT * FROM banner_slides WHERE id = ?', [$id]) : null;
if (is_post()) {
    $t = fn($k, $n = 190) => nullable(mb_substr(input($k, '', 'post'), 0, $n));
    $d = [
        'eyebrow' => $t('eyebrow', 120), 'heading' => $t('heading'), 'subheading' => $t('subheading', 255), 'description' => $t('description', 1000),
        'btn1_text' => $t('btn1_text', 60), 'btn1_url' => $t('btn1_url', 500), 'btn2_text' => $t('btn2_text', 60), 'btn2_url' => $t('btn2_url', 500),
        'text_align' => in_array(input('text_align', '', 'post'), ['left', 'center', 'right'], true) ? input('text_align', '', 'post') : 'left',
        'text_position' => in_array(input('text_position', '', 'post'), ['top', 'middle', 'bottom'], true) ? input('text_position', '', 'post') : 'middle',
        'text_color' => safe_color(input('text_color', '', 'post'), '#FFFFFF'), 'overlay_color' => safe_color(input('overlay_color', '', 'post'), '#000000'),
        'overlay_opacity' => max(0, min(90, input_int('overlay_opacity', 35))), 'height_desktop' => max(30, min(100, input_int('height_desktop', 88))),
        'height_mobile' => max(30, min(100, input_int('height_mobile', 78))),
        'image_position' => preg_match('/^[a-z0-9% ]{3,30}$/i', input('image_position', '', 'post')) ? input('image_position', '', 'post') : 'center center',
        'background_size' => input('background_size', '', 'post') === 'contain' ? 'contain' : 'cover',
        'sort_order' => input_int('sort_order'), 'is_active' => input_bool('is_active'),
    ];
    $d['image_desktop'] = a_try(fn() => a_image_value('image_desktop', $slide['image_desktop'] ?? null), $slide['image_desktop'] ?? null);
    $d['image_mobile'] = a_try(fn() => a_image_value('image_mobile', $slide['image_mobile'] ?? null), $slide['image_mobile'] ?? null);
    $id ? db_update('banner_slides', $d, 'id = ?', [$id]) : ($id = db_insert('banner_slides', $d));
    audit_log('slide_saved', 'banner_slide', $id);
    flash('success', 'Slide saved.');
    redirect('admin/homepage/slide.php?id=' . $id);
}
$s = $slide ?? ['text_align' => 'left', 'text_position' => 'middle', 'text_color' => '#FFFFFF', 'overlay_color' => '#000000', 'overlay_opacity' => 35, 'height_desktop' => 88, 'height_mobile' => 78, 'image_position' => 'center center', 'background_size' => 'cover', 'is_active' => 1, 'sort_order' => 0];
admin_header($id ? 'Edit slide' : 'Add slide');
?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-7">
<div class="card a-card"><div class="card-header">Images</div><div class="card-body">
    <?= a_image('image_desktop', 'Desktop image (landscape, ~2400×1350)', $s['image_desktop'] ?? null) ?>
    <?= a_image('image_mobile', 'Mobile image (portrait, ~900×1400 — optional)', $s['image_mobile'] ?? null) ?>
</div></div>
<div class="card a-card"><div class="card-header">Text &amp; buttons</div><div class="card-body">
    <?= a_input('eyebrow', 'Small heading', $s['eyebrow'] ?? '') ?>
    <?= a_input('heading', 'Main heading', $s['heading'] ?? '', 'text', [], 'e.g. Define Your Style') ?>
    <?= a_input('subheading', 'Subheading', $s['subheading'] ?? '') ?>
    <?= a_textarea('description', 'Supporting description', $s['description'] ?? '', 2) ?>
    <div class="row"><div class="col-6"><?= a_input('btn1_text', 'Primary button text', $s['btn1_text'] ?? '') ?></div><div class="col-6"><?= a_input('btn1_url', 'Primary button link', $s['btn1_url'] ?? '', 'text', ['placeholder' => 'new-arrivals']) ?></div>
    <div class="col-6"><?= a_input('btn2_text', 'Secondary button text', $s['btn2_text'] ?? '') ?></div><div class="col-6"><?= a_input('btn2_url', 'Secondary button link', $s['btn2_url'] ?? '') ?></div></div>
</div></div>
</div><div class="col-lg-5">
<div class="card a-card"><div class="card-header">Layout &amp; style</div><div class="card-body">
    <div class="row">
    <div class="col-6"><?= a_select('text_align', 'Text alignment', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], $s['text_align']) ?></div>
    <div class="col-6"><?= a_select('text_position', 'Text position', ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom'], $s['text_position']) ?></div>
    <div class="col-6"><?= a_color('text_color', 'Text colour', $s['text_color']) ?></div>
    <div class="col-6"><?= a_color('overlay_color', 'Overlay colour', $s['overlay_color']) ?></div>
    <div class="col-12"><label class="form-label">Overlay opacity: <span id="opv"><?= (int) $s['overlay_opacity'] ?></span>%</label><input type="range" class="form-range" name="overlay_opacity" min="0" max="90" value="<?= (int) $s['overlay_opacity'] ?>" oninput="document.getElementById('opv').textContent=this.value"></div>
    <div class="col-6"><?= a_input('height_desktop', 'Height desktop (vh)', $s['height_desktop'], 'number', ['min' => 30, 'max' => 100]) ?></div>
    <div class="col-6"><?= a_input('height_mobile', 'Height mobile (vh)', $s['height_mobile'], 'number', ['min' => 30, 'max' => 100]) ?></div>
    <div class="col-6"><?= a_select('image_position', 'Image focus', ['center center' => 'Center', 'center top' => 'Top', 'center bottom' => 'Bottom', 'left center' => 'Left', 'right center' => 'Right', 'center 30%' => 'Upper third'], $s['image_position']) ?></div>
    <div class="col-6"><?= a_select('background_size', 'Image sizing', ['cover' => 'Fill (cover)', 'contain' => 'Fit (contain)'], $s['background_size']) ?></div>
    <div class="col-6"><?= a_input('sort_order', 'Order', $s['sort_order'], 'number') ?></div>
    <div class="col-6 pt-4"><?= a_check('is_active', 'Active', (int) $s['is_active']) ?></div>
    </div>
    <button class="btn btn-dark w-100">Save slide</button>
    <a class="btn btn-outline-dark w-100 mt-2" href="<?= e(url('?preview=1')) ?>" target="_blank">Preview homepage</a>
</div></div>
</div></div></form>
<?php admin_footer();
