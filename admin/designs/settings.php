<?php
/** Design Studio settings: automatic vs quote pricing, print prices by image size, garments, background removal. */
require __DIR__ . '/../includes/init.php';
admin_require('design_studio');
$num = ['design_price_small', 'design_price_medium', 'design_price_large', 'design_price_text', 'design_multi_discount', 'design_both_sides_fee', 'design_min_print'];
if (is_post()) {
    setting_save('design_enabled', input_bool('design_enabled') ? '1' : '0', 'design');
    setting_save('design_pricing_mode', input('design_pricing_mode', 'auto', 'post') === 'quote' ? 'quote' : 'auto', 'design');
    setting_save('design_show_estimate', input_bool('design_show_estimate') ? '1' : '0', 'design');
    foreach ($num as $k) {
        setting_save($k, (string) max(0, round((float) input_float($k, 0), 2)), 'design');
    }
    $small = min(95, max(1, (float) input_float('design_small_max', 12)));
    setting_save('design_small_max', (string) $small, 'design');
    setting_save('design_medium_max', (string) min(100, max($small + 1, (float) input_float('design_medium_max', 35))), 'design');
    setting_save('design_multi_discount', (string) min(90, max(0, (float) input_float('design_multi_discount', 0))), 'design');
    setting_save('design_production_days', (string) max(0, min(60, input_int('design_production_days', 3))), 'design');
    setting_save('design_max_elements', (string) max(1, min(20, input_int('design_max_elements', 8))), 'design');
    setting_save('design_ai_daily_limit', (string) max(0, min(500, input_int('design_ai_daily_limit', 15))), 'design');
    setting_save('design_notify_email', mb_substr(input('design_notify_email', '', 'post'), 0, 500), 'design');
    if (input_bool('remove_bg_key')) {
        setting_save('design_removebg_key', '', 'design');
    } elseif (($key = trim((string) ($_POST['design_removebg_key'] ?? ''))) !== '') {
        setting_save('design_removebg_key', encrypt_secret($key), 'design');
    }
    audit_log('design_settings_saved', 'settings');
    flash('success', 'Design Studio settings saved.');
    redirect('admin/designs/settings.php');
}
$pr = design_pricing();
$blanks = db_all("SELECT p.id, p.name, p.design_type, p.status, p.price, p.sale_price FROM products p WHERE p.design_type IS NOT NULL AND p.design_type <> '' ORDER BY p.id");
admin_header('Design Studio prices & settings');
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-4"><div class="col-xl-7">
    <div class="card a-card"><div class="card-header">How customers get their price</div><div class="card-body">
        <?= a_check('design_enabled', 'Design Studio is open to customers', setting_bool('design_enabled', true)) ?>
        <div class="a-choice mt-3">
            <label class="form-check"><input class="form-check-input" type="radio" name="design_pricing_mode" value="auto"<?= design_mode() === 'auto' ? ' checked' : '' ?>>
                <span class="form-check-label"><strong>Automatic price</strong> — the price is calculated from the table below and shown instantly; customers add the design to the bag and check out.</span></label>
            <label class="form-check mt-2"><input class="form-check-input" type="radio" name="design_pricing_mode" value="quote"<?= design_mode() === 'quote' ? ' checked' : '' ?>>
                <span class="form-check-label"><strong>Quote — you confirm each price</strong> — customers send the design with sizes and contact details; you check it under <a href="<?= e(url('admin/designs/')) ?>">Design Requests</a>, enter the price, and they order from their link.</span></label>
        </div>
        <div class="mt-2 ms-4"><?= a_check('design_show_estimate', 'In quote mode, show customers the automatic price as an estimate', setting_bool('design_show_estimate', true)) ?></div>
    </div></div>
    <div class="card a-card"><div class="card-header">Print prices <small class="text-muted">— per piece, added to the garment price</small></div><div class="card-body">
        <div class="row g-3">
            <div class="col-sm-4"><?= a_input('design_price_small', 'Small image', $pr['small'], 'number', ['min' => 0, 'step' => '1']) ?></div>
            <div class="col-sm-4"><?= a_input('design_price_medium', 'Medium image', $pr['medium'], 'number', ['min' => 0, 'step' => '1']) ?></div>
            <div class="col-sm-4"><?= a_input('design_price_large', 'Large image', $pr['large'], 'number', ['min' => 0, 'step' => '1']) ?></div>
            <div class="col-sm-6"><?= a_input('design_small_max', 'Small = up to this % of the print area', $pr['small_max'], 'number', ['min' => 1, 'max' => 95, 'step' => '1'], 'Default 12% ≈ 10 × 12 cm on a T-shirt front (chest logo).') ?></div>
            <div class="col-sm-6"><?= a_input('design_medium_max', 'Medium = up to this % (bigger = large)', $pr['medium_max'], 'number', ['min' => 2, 'max' => 100, 'step' => '1'], 'Default 35% ≈ 18 × 24 cm. Above this the image counts as large (A3 / full front).') ?></div>
            <div class="col-sm-4"><?= a_input('design_price_text', 'Each text line', $pr['text'], 'number', ['min' => 0, 'step' => '1']) ?></div>
            <div class="col-sm-4"><?= a_input('design_multi_discount', 'Discount when 2+ images (%)', $pr['multi_discount'], 'number', ['min' => 0, 'max' => 90, 'step' => '1'], 'Taken off the image prices; 0 = no discount.') ?></div>
            <div class="col-sm-4"><?= a_input('design_both_sides_fee', 'Extra for front + back', $pr['both_sides_fee'], 'number', ['min' => 0, 'step' => '1'], 'One-off setup per piece when both sides are printed.') ?></div>
            <div class="col-sm-4"><?= a_input('design_min_print', 'Minimum print charge', $pr['min_print'], 'number', ['min' => 0, 'step' => '1']) ?></div>
        </div>
        <div class="a-help mt-2 small" data-example></div>
    </div></div>
</div><div class="col-xl-5">
    <div class="card a-card"><div class="card-header">Garments (blanks)</div><div class="card-body small">
        <p>Any product becomes a Design Studio garment when you set <em>Design Studio blank</em> on its edit page. Its colours, sizes, price and stock are used — printing uses a blank from stock.</p>
        <ul class="list-unstyled mb-0"><?php foreach ($blanks as $b): ?><li class="d-flex justify-content-between border-bottom py-2"><a href="<?= e(url('admin/products/edit.php?id=' . $b['id'])) ?>"><?= e($b['name']) ?></a><span class="text-muted"><?= e(design_types()[$b['design_type']]['label'] ?? $b['design_type']) ?> · <?= e(money($b['sale_price'] ?: $b['price'])) ?><?= $b['status'] !== 'published' ? ' · <span class="text-danger">' . e($b['status']) . '</span>' : '' ?></span></li><?php endforeach; ?>
            <?php if (!$blanks): ?><li class="text-danger">No garments yet — the Design Studio stays hidden until you set at least one.</li><?php endif; ?></ul>
    </div></div>
    <div class="card a-card"><div class="card-header">Production &amp; limits</div><div class="card-body">
        <?= a_input('design_production_days', 'Printing time (working days, added to delivery estimate)', design_production_days(), 'number', ['min' => 0]) ?>
        <?= a_input('design_max_elements', 'Maximum images + texts per design', design_max_elements(), 'number', ['min' => 1, 'max' => 20]) ?>
        <?= a_input('design_notify_email', 'Email new design requests to (blank = store email)', setting('design_notify_email')) ?>
    </div></div>
    <div class="card a-card"><div class="card-header">AI background removal (optional)</div><div class="card-body">
        <p class="small">“Remove background” works for every customer out of the box (best on plain backgrounds). For people and busy photos you can add an <strong>AI remove</strong> button powered by <a href="https://www.remove.bg/api" target="_blank" rel="noopener">remove.bg</a> (paid per image). The key is stored encrypted and never shown in the browser.</p>
        <?= a_input('design_removebg_key', design_ai_bg_enabled() ? 'remove.bg API key (saved — enter a new one to replace)' : 'remove.bg API key', '', 'password', ['autocomplete' => 'new-password']) ?>
        <?php if (design_ai_bg_enabled()): ?><?= a_check('remove_bg_key', 'Remove the saved key', false) ?><?php endif; ?>
        <?= a_input('design_ai_daily_limit', 'AI removals per customer per day', (int) setting('design_ai_daily_limit', 15), 'number', ['min' => 0]) ?>
    </div></div>
</div></div>
<button class="btn btn-dark">Save settings</button>
</form>
<script>
(function () {
    var f = document.querySelector('form'), box = document.querySelector('[data-example]');
    function v(n) { return parseFloat(f.elements[n].value) || 0; }
    function show() {
        var img = v('design_price_medium') + v('design_price_small'), disc = Math.round(img * v('design_multi_discount')) / 100;
        var print = Math.max(v('design_min_print'), img - disc + v('design_price_text') + v('design_both_sides_fee'));
        box.innerHTML = '<strong>Example:</strong> medium image on the front, small logo + one text line on the back = <strong>Rs. ' + Math.round(print).toLocaleString('en-US') + '</strong> print per piece, plus the garment price.';
    }
    f.addEventListener('input', show); show();
})();
</script>
<?php admin_footer();
