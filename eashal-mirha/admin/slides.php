<?php
require __DIR__ . '/includes/admin.php';
require_section('slides');

$id = (int)get('id');
$editing = get('new') === '1' || $id;
$s = $id ? row('SELECT * FROM slides WHERE id = ?', [$id]) : null;
$blank = ['small_text' => '', 'heading' => '', 'subheading' => '', 'btn_text' => '', 'btn_link' => '', 'btn2_text' => '', 'btn2_link' => '', 'image' => '', 'mobile_image' => '', 'text_color' => '#ffffff', 'overlay' => '0.35', 'align' => 'left', 'sort_order' => 0, 'status' => 1];
$d = $s ?: $blank;

if (is_post()) {
    require_csrf();
    $do = post('do');
    if ($do === 'settings') {
        save_posted_settings(['hero_height', 'hero_height_unit', 'hero_height_mobile', 'hero_width', 'hero_max_width', 'hero_autoplay', 'hero_effect', 'hero_heading_size', 'hero_heading_size_mobile', 'hero_kenburns']);
        flash('success', 'Hero banner settings saved.');
        redirect('admin/slides');
    }
    if ($do === 'delete') {
        $del = row('SELECT * FROM slides WHERE id = ?', [(int)post('id')]);
        if ($del) { delete_upload($del['image']); delete_upload($del['mobile_image']); q('DELETE FROM slides WHERE id = ?', [$del['id']]); }
        flash('success', 'Slide deleted.');
        redirect('admin/slides');
    }
    if ($do === 'toggle') {
        q('UPDATE slides SET status = 1 - status WHERE id = ?', [(int)post('id')]);
        redirect('admin/slides');
    }
    if ($do === 'move') {
        $list = rows('SELECT id FROM slides ORDER BY sort_order, id');
        $ids = array_column($list, 'id');
        $pos = array_search((int)post('id'), array_map('intval', $ids), true);
        $swap = post('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) { [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]]; }
        foreach ($ids as $i => $sid) q('UPDATE slides SET sort_order = ? WHERE id = ?', [$i + 1, $sid]);
        redirect('admin/slides');
    }
    // save slide
    foreach ($blank as $k => $v) if (!in_array($k, ['image', 'mobile_image'], true)) $d[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $v;
    $d['status'] = post('status') === '1' ? 1 : 0;
    $image = handle_image('image', $s['image'] ?? null, 'banners');
    $mobile = handle_image('mobile_image', $s['mobile_image'] ?? null, 'banners');
    if (!$image) {
        flash('error', 'Please upload a background image for the slide.');
    } else {
        $vals = [$d['small_text'], $d['heading'], $d['subheading'], $d['btn_text'], $d['btn_link'], $d['btn2_text'], $d['btn2_link'], $image, $mobile,
            preg_match('~^#[0-9a-f]{3,8}$~i', $d['text_color']) ? $d['text_color'] : '#ffffff', max(0, min(1, (float)$d['overlay'])),
            in_array($d['align'], ['left', 'center', 'right'], true) ? $d['align'] : 'left', (int)$d['sort_order'], $d['status']];
        $set = 'small_text=?, heading=?, subheading=?, btn_text=?, btn_link=?, btn2_text=?, btn2_link=?, image=?, mobile_image=?, text_color=?, overlay=?, align=?, sort_order=?, status=?';
        if ($id) q("UPDATE slides SET $set WHERE id = ?", array_merge($vals, [$id]));
        else q("INSERT INTO slides SET $set", $vals);
        flash('success', 'Slide saved.');
        redirect('admin/slides');
    }
}

$slides = rows('SELECT * FROM slides ORDER BY sort_order, id');
admin_header('Hero Banners', 'slides');

if ($editing): ?>
  <p><a class="link" href="<?= url('admin/slides') ?>">← All slides</a></p>
  <form method="post" enctype="multipart/form-data" class="edit-layout">
    <?= csrf_field() ?>
    <div class="edit-main">
      <div class="card">
        <div class="card__head"><h3>Live preview</h3></div>
        <div class="slide-preview align-<?= e($d['align']) ?>" data-slide-preview style="background-image:url('<?= e(img($d['image'])) ?>')">
          <div class="slide-preview__ov" style="opacity:<?= (float)$d['overlay'] ?>"></div>
          <div class="slide-preview__text" style="color:<?= e($d['text_color']) ?>">
            <small data-pv="small_text"><?= e($d['small_text']) ?></small>
            <strong data-pv="heading"><?= e($d['heading']) ?></strong>
            <p data-pv="subheading"><?= e($d['subheading']) ?></p>
            <span class="pv-btn" data-pv="btn_text"><?= e($d['btn_text']) ?></span>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card__head"><h3>Text</h3></div>
        <?= f_text('small_text', 'Small text (above heading)', $d['small_text'], ['attrs' => 'data-live="small_text"']) ?>
        <?= f_text('heading', 'Heading', $d['heading'], ['attrs' => 'data-live="heading"']) ?>
        <?= f_text('subheading', 'Sub-heading', $d['subheading'], ['type' => 'textarea', 'rows' => 2, 'attrs' => 'data-live="subheading"']) ?>
        <div class="row-2"><?= f_text('btn_text', 'Button 1 text', $d['btn_text'], ['attrs' => 'data-live="btn_text"']) ?><?= f_text('btn_link', 'Button 1 link', $d['btn_link'], ['help' => 'e.g. category/bridal or shop?filter=new or a full URL']) ?></div>
        <div class="row-2"><?= f_text('btn2_text', 'Button 2 text', $d['btn2_text']) ?><?= f_text('btn2_link', 'Button 2 link', $d['btn2_link']) ?></div>
      </div>
      <div class="card">
        <div class="card__head"><h3>Background</h3></div>
        <div class="row-2">
          <?= f_image('image', 'Desktop image *', $d['image'], 'Recommended 1920×1000 (JPG/WEBP)') ?>
          <?= f_image('mobile_image', 'Mobile image (optional)', $d['mobile_image'], 'Portrait 900×1400 — shown on phones') ?>
        </div>
      </div>
    </div>
    <aside class="edit-side">
      <div class="card sticky">
        <div class="card__head"><h3>Style</h3></div>
        <?= f_select('align', 'Text alignment', $d['align'], ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], ['attrs' => 'data-live-align']) ?>
        <label class="field"><span>Text colour</span><input type="color" name="text_color" value="<?= e($d['text_color']) ?>" data-live-color></label>
        <label class="field"><span>Dark overlay: <b data-range-out><?= round((float)$d['overlay'] * 100) ?>%</b></span><input type="range" name="overlay" min="0" max="0.9" step="0.05" value="<?= e($d['overlay']) ?>" data-live-overlay></label>
        <?= f_text('sort_order', 'Sort order', $d['sort_order'], ['type' => 'number']) ?>
        <?= f_switch('status', 'Active', $d['status']) ?>
        <button class="btn btn-primary btn-block">Save Slide</button>
      </div>
    </aside>
  </form>
<?php else: ?>
  <div class="grid-2 wide-left">
    <div class="card">
      <div class="card__head"><h3>Slides</h3><a class="btn btn-primary btn-sm" href="?new=1"><?= aicon('plus') ?> Add Slide</a></div>
      <div class="slide-list">
        <?php foreach ($slides as $sl): ?>
          <div class="slide-row<?= $sl['status'] ? '' : ' off' ?>">
            <img src="<?= e(img($sl['image'])) ?>" alt="">
            <div class="slide-row__info"><small><?= e($sl['small_text']) ?></small><strong><?= e($sl['heading'] ?: '(no heading)') ?></strong><span class="muted sm">Align <?= e($sl['align']) ?> · <?= $sl['status'] ? 'Active' : 'Hidden' ?></span></div>
            <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sl['id'] ?>">
              <button class="icon" name="do" value="move" onclick="this.form.dir.value='up'" title="Move up"><?= aicon('up') ?></button>
              <button class="icon" name="do" value="move" onclick="this.form.dir.value='down'" title="Move down"><?= aicon('down') ?></button>
              <input type="hidden" name="dir" value="">
              <button class="icon" name="do" value="toggle" title="Show / hide"><?= aicon('eye') ?></button>
              <a class="icon" href="?id=<?= $sl['id'] ?>" title="Edit"><?= aicon('edit') ?></a>
              <button class="icon danger" name="do" value="delete" data-confirm="Delete this slide?" title="Delete"><?= aicon('trash') ?></button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$slides): ?><p class="empty">No slides yet — add your first hero banner.</p><?php endif; ?>
      </div>
    </div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="do" value="settings">
      <div class="card__head"><h3>Banner size & behaviour</h3></div>
      <div class="row-2">
        <?= f_text('hero_height', 'Desktop height', setting('hero_height', 92), ['type' => 'number']) ?>
        <?= f_select('hero_height_unit', 'Unit', setting('hero_height_unit', 'vh'), ['vh' => '% of screen (vh)', 'px' => 'Pixels (px)']) ?>
      </div>
      <?= f_text('hero_height_mobile', 'Mobile height (same unit)', setting('hero_height_mobile', 78), ['type' => 'number']) ?>
      <div class="row-2">
        <?= f_select('hero_width', 'Width', setting('hero_width', 'full'), ['full' => 'Full width', 'boxed' => 'Boxed']) ?>
        <?= f_text('hero_max_width', 'Boxed max width (px)', setting('hero_max_width', 1400), ['type' => 'number']) ?>
      </div>
      <div class="row-2">
        <?= f_text('hero_heading_size', 'Heading size desktop (px)', setting('hero_heading_size', 64), ['type' => 'number']) ?>
        <?= f_text('hero_heading_size_mobile', 'Heading size mobile (px)', setting('hero_heading_size_mobile', 36), ['type' => 'number']) ?>
      </div>
      <div class="row-2">
        <?= f_select('hero_effect', 'Transition', setting('hero_effect', 'fade'), ['fade' => 'Fade', 'slide' => 'Slide']) ?>
        <?= f_text('hero_autoplay', 'Autoplay delay (ms)', setting('hero_autoplay', 6000), ['type' => 'number', 'attrs' => 'step="500" min="2000"']) ?>
      </div>
      <?= f_switch('hero_kenburns', 'Slow zoom (Ken Burns) animation', setting('hero_kenburns', '1') === '1') ?>
      <button class="btn btn-primary btn-block">Save Settings</button>
    </form>
  </div>
<?php endif;
admin_footer();
