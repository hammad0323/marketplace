<?php
/** Hero banner slides. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('homepage.manage');
$errors = [];
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'reorder') {
        foreach (array_values((array)($_POST['ids'] ?? [])) as $i => $sid) db_exec('UPDATE banner_slides SET sort_order = ? WHERE id = ?', [$i + 1, (int)$sid]);
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        db_exec('DELETE FROM banner_slides WHERE id = ?', [(int)post('id')]);
        audit('slide_delete', 'slide', (int)post('id'));
        flash('success', 'Slide deleted.');
        redirect(admin_url('slides'));
    }
    if ($act === 'save') {
        $sid = (int)post('id');
        $old = $sid ? db_one('SELECT * FROM banner_slides WHERE id = ?', [$sid]) : null;
        try {
            $desk = f_image_value('desktop_image', $old['desktop_image'] ?? null, 'banners');
            if (!$desk) throw new RuntimeException('A desktop image is required.');
            $hex = fn($k, $d) => v_hex(post($k)) ? strtoupper(post($k)) : $d;
            $d = [
                'desktop_image' => $desk, 'mobile_image' => f_image_value('mobile_image', $old['mobile_image'] ?? null, 'banners'),
                'image_alt' => mb_substr(post('image_alt'), 0, 255) ?: null, 'heading' => mb_substr(post('heading'), 0, 190) ?: null,
                'subheading' => mb_substr(post('subheading'), 0, 190) ?: null, 'description' => mb_substr(post('description'), 0, 500) ?: null,
                'btn1_text' => mb_substr(post('btn1_text'), 0, 60) ?: null, 'btn1_url' => clean_url(post('btn1_url')) ?: null,
                'btn2_text' => mb_substr(post('btn2_text'), 0, 60) ?: null, 'btn2_url' => clean_url(post('btn2_url')) ?: null,
                'text_align' => in_list(post('text_align'), ['left', 'center', 'right'], 'left'), 'text_position' => in_list(post('text_position'), ['top', 'middle', 'bottom'], 'middle'),
                'content_side' => in_list(post('content_side'), ['start', 'center', 'end'], 'start'), 'text_color' => $hex('text_color', '#FFFFFF'),
                'overlay_color' => $hex('overlay_color', '#332820'), 'overlay_opacity' => clamp_int(post('overlay_opacity'), 0, 90),
                'height_desktop' => clamp_int(post('height_desktop'), 320, 1200), 'height_mobile' => clamp_int(post('height_mobile'), 280, 1000),
                'bg_position' => in_list(post('bg_position'), ['center center', 'center top', 'center bottom', 'left center', 'right center'], 'center center'),
                'bg_size' => in_list(post('bg_size'), ['cover', 'contain', 'auto'], 'cover'), 'parallax' => post('parallax') ? 1 : 0,
                'is_active' => post('is_active') ? 1 : 0, 'sort_order' => (int)post('sort_order'),
            ];
            if ($sid) db_exec('UPDATE banner_slides SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', array_merge(array_values($d), [$sid]));
            else $sid = db_insert('INSERT INTO banner_slides (' . implode(',', array_keys($d)) . ') VALUES (' . db_in($d) . ')', array_values($d));
            audit('slide_save', 'slide', $sid);
            flash('success', 'Slide saved.');
            redirect(admin_url('slides?edit=' . $sid));
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}
$slides = db_all('SELECT * FROM banner_slides ORDER BY sort_order, id');
$edit = get('edit') !== '' ? (db_one('SELECT * FROM banner_slides WHERE id = ?', [(int)get('edit')]) ?: ['id' => 0]) : null;
$admin_title = 'Hero slides';
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-4">
    <div class="d-flex mb-2"><span class="small text-muted">Autoplay, duration and transition: Homepage builder → Hero.</span><a href="?edit=0" class="btn btn-sm btn-primary ms-auto">Add slide</a></div>
    <div data-sortable="<?= e(admin_url('slides')) ?>">
      <?php foreach ($slides as $s): ?><div class="section-row" data-id="<?= (int)$s['id'] ?>"><i class="bi bi-grip-vertical drag-handle"></i><img src="<?= e(img_url($s['desktop_image'])) ?>" style="width:90px;height:50px;object-fit:cover;border-radius:4px" alt="">
        <div class="flex-grow-1 small"><strong><?= e($s['heading'] ?: '(no heading)') ?></strong><br><?= $s['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-muted">Inactive</span>' ?></div>
        <a class="btn btn-sm btn-light" href="?edit=<?= (int)$s['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" data-confirm="Delete this slide?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div><?php endforeach; ?>
    </div>
  </div>
  <div class="col-xl-8"><?php if ($edit !== null): $sv = fn($k, $d = '') => $edit[$k] ?? $d; ?>
    <div class="card"><div class="card-header"><?= $sv('id') ? 'Edit slide' : 'New slide' ?></div><div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$sv('id', 0) ?>">
      <div class="col-md-6"><?= f_image('desktop_image', 'Desktop image (recommended 2400×1250)', $sv('desktop_image') ?: null) ?></div>
      <div class="col-md-6"><?= f_image('mobile_image', 'Mobile image (recommended 1000×1400, optional)', $sv('mobile_image') ?: null) ?></div>
      <div class="col-md-6"><?= f_text('heading', 'Main heading', $sv('heading')) ?></div>
      <div class="col-md-6"><?= f_text('subheading', 'Subheading (small text above)', $sv('subheading')) ?></div>
      <div class="col-12"><?= f_text('description', 'Supporting description', $sv('description'), ['type' => 'textarea', 'rows' => 2]) ?></div>
      <div class="col-md-3"><?= f_text('btn1_text', 'Primary button', $sv('btn1_text')) ?></div><div class="col-md-3"><?= f_text('btn1_url', 'Primary URL', $sv('btn1_url'), ['placeholder' => '/shop']) ?></div>
      <div class="col-md-3"><?= f_text('btn2_text', 'Secondary button', $sv('btn2_text')) ?></div><div class="col-md-3"><?= f_text('btn2_url', 'Secondary URL', $sv('btn2_url')) ?></div>
      <div class="col-md-3"><?= f_select('text_align', 'Text alignment', ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'], $sv('text_align', 'left')) ?></div>
      <div class="col-md-3"><?= f_select('content_side', 'Content position (horizontal)', ['start' => 'Left', 'center' => 'Centre', 'end' => 'Right'], $sv('content_side', 'start')) ?></div>
      <div class="col-md-3"><?= f_select('text_position', 'Content position (vertical)', ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom'], $sv('text_position', 'middle')) ?></div>
      <div class="col-md-3"><?= f_text('text_color', 'Text colour', $sv('text_color', '#FFFFFF'), ['type' => 'color']) ?></div>
      <div class="col-md-3"><?= f_text('overlay_color', 'Overlay colour', $sv('overlay_color', '#332820'), ['type' => 'color']) ?></div>
      <div class="col-md-3"><?= f_text('overlay_opacity', 'Overlay opacity (0–90%)', $sv('overlay_opacity', 30), ['type' => 'number', 'min' => 0, 'max' => 90]) ?></div>
      <div class="col-md-3"><?= f_text('height_desktop', 'Desktop height (px)', $sv('height_desktop', 760), ['type' => 'number']) ?></div>
      <div class="col-md-3"><?= f_text('height_mobile', 'Mobile height (px)', $sv('height_mobile', 620), ['type' => 'number']) ?></div>
      <div class="col-md-3"><?= f_select('bg_position', 'Image focus', ['center center' => 'Centre', 'center top' => 'Top', 'center bottom' => 'Bottom', 'left center' => 'Left', 'right center' => 'Right'], $sv('bg_position', 'center center')) ?></div>
      <div class="col-md-3"><?= f_select('bg_size', 'Image sizing', ['cover' => 'Fill (cover)', 'contain' => 'Fit (contain)', 'auto' => 'Original'], $sv('bg_size', 'cover')) ?></div>
      <div class="col-md-3"><?= f_text('image_alt', 'Image alt text', $sv('image_alt')) ?></div>
      <div class="col-md-3"><?= f_text('sort_order', 'Display order', $sv('sort_order', 0), ['type' => 'number']) ?></div>
      <div class="col-md-3"><?= f_toggle('parallax', 'Parallax effect', $sv('parallax')) ?></div>
      <div class="col-md-3"><?= f_toggle('is_active', 'Active', $sv('is_active', 1)) ?></div>
      <div class="col-12"><button class="btn btn-primary">Save slide</button> <a class="btn btn-outline-secondary" href="<?= e(url()) ?>" target="_blank">View homepage</a></div>
    </form></div></div>
  <?php endif; ?></div>
</div>
<?php require __DIR__ . '/partials/footer.php';
