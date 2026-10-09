<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.slides');
$id = input_int('id', 0, 'get');
$slide = $id ? db_one('SELECT * FROM banner_slides WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    require_csrf();
    $hex = fn($k, $def) => valid_hex(input($k)) ? strtoupper(input($k)) : $def;
    $d = [
        'title' => mb_substr(input('title'), 0, 190), 'subtitle' => mb_substr(input('subtitle'), 0, 190) ?: null,
        'description' => mb_substr(input('description'), 0, 500) ?: null, 'image_alt' => mb_substr(input('image_alt'), 0, 190) ?: null,
        'primary_btn_text' => mb_substr(input('primary_btn_text'), 0, 60) ?: null, 'primary_btn_url' => mb_substr(input('primary_btn_url'), 0, 255) ?: null,
        'secondary_btn_text' => mb_substr(input('secondary_btn_text'), 0, 60) ?: null, 'secondary_btn_url' => mb_substr(input('secondary_btn_url'), 0, 255) ?: null,
        'text_align' => in_array(input('text_align'), ['left', 'center', 'right'], true) ? input('text_align') : 'left',
        'content_position' => in_array(input('content_position'), ['top', 'middle', 'bottom'], true) ? input('content_position') : 'middle',
        'text_color' => $hex('text_color', '#FFFFFF'), 'accent_color' => $hex('accent_color', '#B99A5B'),
        'btn_bg_color' => $hex('btn_bg_color', '#214E9B'), 'btn_text_color' => $hex('btn_text_color', '#FFFFFF'),
        'overlay_color' => $hex('overlay_color', '#0A1426'), 'overlay_opacity' => max(0, min(90, input_int('overlay_opacity', 40))),
        'height_desktop' => max(320, min(1200, input_int('height_desktop', 680))), 'height_mobile' => max(320, min(1200, input_int('height_mobile', 560))),
        'bg_position' => preg_match('/^[a-z0-9% .]{3,30}$/i', input('bg_position')) ? input('bg_position') : 'center center',
        'bg_size' => in_array(input('bg_size'), ['cover', 'contain', 'auto'], true) ? input('bg_size') : 'cover',
        'sort_order' => input_int('sort_order'), 'is_active' => input_bool('is_active'),
        'starts_at' => input('starts_at') ? date('Y-m-d H:i:s', strtotime(input('starts_at'))) : null,
        'ends_at' => input('ends_at') ? date('Y-m-d H:i:s', strtotime(input('ends_at'))) : null,
    ];
    if ($d['title'] === '') {
        $errors[] = 'Heading is required.';
    }
    foreach (['primary_btn_url', 'secondary_btn_url'] as $k) {
        if ($d[$k] && safe_link($d[$k], '') === '') {
            $errors[] = 'Button links must be site paths (/shop) or full https:// URLs.';
        }
    }
    [$desk, $e1] = handle_image_field('image_desktop', $slide['image_desktop'] ?? null, 'slides');
    [$mob, $e2] = handle_image_field('image_mobile', $slide['image_mobile'] ?? null, 'slides');
    foreach (array_filter([$e1, $e2]) as $er) {
        $errors[] = $er;
    }
    if (!$desk) {
        $errors[] = 'A desktop image is required.';
    }
    $d['image_desktop'] = (string) $desk;
    $d['image_mobile'] = $mob ?: null;
    if (!$errors) {
        $slide ? db_update('banner_slides', $d, 'id = ?', [$id]) : ($id = db_insert('banner_slides', $d));
        audit_log($slide ? 'slide_updated' : 'slide_created', 'slide', $id, ['title' => $d['title']]);
        flash('success', 'Slide saved.');
        redirect(admin_url('slide-edit', ['id' => $id]));
    }
    $slide = array_merge($slide ?? [], $d);
}
$s = $slide ?? ['text_align' => 'left', 'content_position' => 'middle', 'text_color' => '#FFFFFF', 'accent_color' => '#B99A5B', 'btn_bg_color' => '#214E9B', 'btn_text_color' => '#FFFFFF', 'overlay_color' => '#0A1426', 'overlay_opacity' => 35, 'height_desktop' => 760, 'height_mobile' => 640, 'bg_position' => 'center center', 'bg_size' => 'cover', 'sort_order' => 0, 'is_active' => 1];
admin_header($id ? 'Edit slide' : 'Add slide', 'slides');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card mb-3"><div class="card-header">Content</div><div class="card-body">
    <?= f_text('subtitle', 'Eyebrow / subheading', $s['subtitle'] ?? '') ?>
    <?= f_text('title', 'Main heading', $s['title'] ?? '', ['required' => true]) ?>
    <?= f_textarea('description', 'Description', $s['description'] ?? '', ['rows' => 2, 'maxlength' => 500]) ?>
    <div class="row"><div class="col-md-6"><?= f_text('primary_btn_text', 'Primary button text', $s['primary_btn_text'] ?? '') ?></div><div class="col-md-6"><?= f_text('primary_btn_url', 'Primary button link', $s['primary_btn_url'] ?? '', [], 'e.g. /shop') ?></div>
      <div class="col-md-6"><?= f_text('secondary_btn_text', 'Secondary button text (optional)', $s['secondary_btn_text'] ?? '') ?></div><div class="col-md-6"><?= f_text('secondary_btn_url', 'Secondary button link', $s['secondary_btn_url'] ?? '') ?></div></div>
  </div></div>
  <div class="card"><div class="card-header">Images</div><div class="card-body">
    <?= f_image('image_desktop', 'Desktop image (2400×1200 recommended)', $s['image_desktop'] ?? null) ?>
    <?= f_image('image_mobile', 'Mobile image (900×1400 recommended, optional)', $s['image_mobile'] ?? null) ?>
    <?= f_text('image_alt', 'Image alt text', $s['image_alt'] ?? '') ?>
    <div class="row"><div class="col-md-6"><?= f_select('bg_position', 'Image focal position', ['center center' => 'Center', 'left center' => 'Left', 'right center' => 'Right', 'center top' => 'Top', 'center bottom' => 'Bottom', '70% center' => 'Right of centre', '30% center' => 'Left of centre'], $s['bg_position']) ?></div>
      <div class="col-md-6"><?= f_select('bg_size', 'Image sizing', ['cover' => 'Cover (fill)', 'contain' => 'Contain (fit)', 'auto' => 'Original size'], $s['bg_size']) ?></div></div>
  </div></div></div>
  <div class="col-lg-5"><div class="card mb-3"><div class="card-header">Layout & colours</div><div class="card-body">
    <div class="row"><div class="col-6"><?= f_select('text_align', 'Text alignment', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], $s['text_align']) ?></div><div class="col-6"><?= f_select('content_position', 'Vertical position', ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom'], $s['content_position']) ?></div>
      <div class="col-6"><?= f_color('text_color', 'Text colour', $s['text_color']) ?></div><div class="col-6"><?= f_color('accent_color', 'Accent (eyebrow)', $s['accent_color']) ?></div>
      <div class="col-6"><?= f_color('btn_bg_color', 'Button background', $s['btn_bg_color']) ?></div><div class="col-6"><?= f_color('btn_text_color', 'Button text', $s['btn_text_color']) ?></div>
      <div class="col-6"><?= f_color('overlay_color', 'Overlay colour', $s['overlay_color']) ?></div><div class="col-6"><?= f_number('overlay_opacity', 'Overlay opacity (0–90%)', $s['overlay_opacity'], ['min' => 0, 'max' => 90, 'step' => 1]) ?></div>
      <div class="col-6"><?= f_number('height_desktop', 'Height desktop (px)', $s['height_desktop'], ['min' => 320, 'max' => 1200, 'step' => 10]) ?></div><div class="col-6"><?= f_number('height_mobile', 'Height mobile (px)', $s['height_mobile'], ['min' => 320, 'max' => 1200, 'step' => 10]) ?></div></div>
  </div></div>
  <div class="card"><div class="card-header">Visibility</div><div class="card-body">
    <?= f_check('is_active', 'Enabled', (int) $s['is_active']) ?>
    <?= f_number('sort_order', 'Order', $s['sort_order'], ['step' => 1]) ?>
    <div class="row"><div class="col-6"><?= f_text('starts_at', 'Show from', !empty($s['starts_at']) ? date('Y-m-d\TH:i', strtotime($s['starts_at'])) : '', [], null, 'datetime-local') ?></div><div class="col-6"><?= f_text('ends_at', 'Show until', !empty($s['ends_at']) ? date('Y-m-d\TH:i', strtotime($s['ends_at'])) : '', [], null, 'datetime-local') ?></div></div>
  </div></div></div>
</div>
<div class="sticky-actions"><?= f_submit() ?> <a class="btn btn-light" href="<?= e(admin_url('slides')) ?>">Back</a> <a class="btn btn-light" href="<?= e(path_url('/')) ?>" target="_blank">View homepage</a></div>
</form>
<?php admin_footer();
