<?php
require __DIR__ . '/includes/admin.php';
require_section('homepage');

if (is_post()) {
    require_csrf();
    if (post('do') === 'sections') {
        foreach ((array)($_POST['sec'] ?? []) as $sid => $v) {
            q('UPDATE home_sections SET title = ?, subtitle = ?, item_limit = ?, sort_order = ?, enabled = ? WHERE id = ?', [
                trim((string)($v['title'] ?? '')), trim((string)($v['subtitle'] ?? '')), max(0, (int)($v['item_limit'] ?? 0)), (int)($v['sort_order'] ?? 0), ($v['enabled'] ?? '0') === '1' ? 1 : 0, (int)$sid,
            ]);
        }
        flash('success', 'Homepage sections saved.');
    }
    if (post('do') === 'banner') {
        $b = row('SELECT * FROM banners WHERE id = ?', [(int)post('id')]);
        if ($b) {
            $isFeature = strpos($b['position'], 'feature_') === 0;
            $image = $isFeature ? post('image') : handle_image('image', $b['image'], 'banners');
            q('UPDATE banners SET small_text = ?, heading = ?, text = ?, btn_text = ?, btn_link = ?, image = ?, height = ?, text_color = ?, overlay = ?, status = ? WHERE id = ?', [
                post('small_text'), post('heading'), post('text'), post('btn_text'), post('btn_link'), $image, max(200, (int)post('height', 520)),
                preg_match('~^#[0-9a-f]{3,8}$~i', post('text_color')) ? post('text_color') : '#ffffff', max(0, min(1, (float)post('overlay'))), post('status') === '1' ? 1 : 0, $b['id'],
            ]);
            flash('success', $b['label'] . ' saved.');
        }
    }
    redirect('admin/homepage' . (post('do') === 'banner' ? '#b' . post('id') : ''));
}

$sections = rows('SELECT * FROM home_sections ORDER BY sort_order, id');
$banners = rows('SELECT * FROM banners ORDER BY id');
$featureIcons = ['truck' => 'Truck / delivery', 'cash' => 'Cash', 'needle' => 'Needle / craft', 'refresh' => 'Exchange', 'shield' => 'Shield / secure', 'gift' => 'Gift', 'star' => 'Star', 'heart' => 'Heart', 'phone' => 'Phone', 'clock' => 'Clock'];
admin_header('Homepage Sections', 'homepage');
?>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="do" value="sections">
  <div class="card__head"><h3>Sections — order, visibility & headings</h3><button class="btn btn-primary btn-sm">Save Sections</button></div>
  <p class="muted sm">Lower numbers appear first. Hero slides are managed in <a class="link" href="<?= url('admin/slides') ?>">Hero Banners</a>; categories shown are those marked “Show on homepage”.</p>
  <div class="table-wrap"><table class="table sections-table">
    <thead><tr><th>On</th><th>Order</th><th>Section</th><th>Title</th><th>Subtitle</th><th>Items</th></tr></thead>
    <tbody>
    <?php foreach ($sections as $s): $noText = in_array($s['skey'], ['hero', 'parallax', 'promo_duo', 'features'], true); ?>
      <tr>
        <td><label class="switch sm"><input type="hidden" name="sec[<?= $s['id'] ?>][enabled]" value="0"><input type="checkbox" name="sec[<?= $s['id'] ?>][enabled]" value="1" <?= $s['enabled'] ? 'checked' : '' ?>><i></i></label></td>
        <td><input class="w-num" type="number" name="sec[<?= $s['id'] ?>][sort_order]" value="<?= (int)$s['sort_order'] ?>"></td>
        <td><strong><?= e($s['label']) ?></strong></td>
        <td><?php if (!$noText): ?><input type="text" name="sec[<?= $s['id'] ?>][title]" value="<?= e($s['title']) ?>"><?php else: ?><span class="muted sm">Edited below / in its own page</span><?php endif; ?></td>
        <td><?php if (!$noText): ?><input type="text" name="sec[<?= $s['id'] ?>][subtitle]" value="<?= e($s['subtitle']) ?>"><?php endif; ?></td>
        <td><?php if (in_array($s['skey'], ['categories', 'new_arrivals', 'best_sellers', 'category_tabs', 'testimonials'], true)): ?><input class="w-num" type="number" name="sec[<?= $s['id'] ?>][item_limit]" value="<?= (int)$s['item_limit'] ?>" min="1"><?php else: ?><input type="hidden" name="sec[<?= $s['id'] ?>][item_limit]" value="0">—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</form>

<h2 class="section-heading">Banners</h2>
<div class="grid-2">
<?php foreach ($banners as $b): $isFeature = strpos($b['position'], 'feature_') === 0; ?>
  <form method="post" enctype="multipart/form-data" class="card" id="b<?= $b['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="do" value="banner"><input type="hidden" name="id" value="<?= $b['id'] ?>">
    <div class="card__head"><h3><?= e($b['label']) ?></h3><?= f_switch('status', 'Show', $b['status']) ?></div>
    <?php if (!$isFeature): ?>
      <div class="banner-thumb" style="background-image:url('<?= e(img($b['image'])) ?>')"></div>
      <?= f_text('small_text', 'Small text', $b['small_text']) ?>
    <?php endif; ?>
    <?= f_text('heading', 'Heading', $b['heading']) ?>
    <?= f_text('text', 'Text', $b['text'], ['type' => 'textarea', 'rows' => 2]) ?>
    <?php if ($isFeature): ?>
      <?= f_select('image', 'Icon', $b['image'], $featureIcons) ?>
    <?php else: ?>
      <div class="row-2"><?= f_text('btn_text', 'Button text', $b['btn_text']) ?><?= f_text('btn_link', 'Button link', $b['btn_link']) ?></div>
      <?= f_image('image', 'Background image', $b['image'], 'Wide image, e.g. 1920×900') ?>
      <div class="row-3">
        <?= f_text('height', 'Height (px)', $b['height'], ['type' => 'number', 'attrs' => 'min="200" step="10"']) ?>
        <label class="field"><span>Text colour</span><input type="color" name="text_color" value="<?= e($b['text_color']) ?>"></label>
        <?= f_text('overlay', 'Overlay (0–0.9)', $b['overlay'], ['type' => 'number', 'attrs' => 'min="0" max="0.9" step="0.05"']) ?>
      </div>
    <?php endif; ?>
    <?php if ($isFeature): ?><input type="hidden" name="height" value="0"><input type="hidden" name="text_color" value="#ffffff"><input type="hidden" name="overlay" value="0"><?php endif; ?>
    <button class="btn btn-primary btn-sm">Save <?= e($b['label']) ?></button>
  </form>
<?php endforeach; ?>
</div>
<?php admin_footer();
