<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
if (is_post()) {
    owner_require_active('owner/pricing.php');
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && !db_val('SELECT 1 FROM shop_pricing WHERE id = ? AND shop_id = ?', [$id, $sid])) {
            redirect('owner/pricing.php');
        }
        $err = pricing_rule_save($sid, $_POST, $id ?: null);
        flash($err ? 'error' : 'success', $err ?: ($id ? 'Price updated.' : 'Price added.'));
    } elseif ($a === 'delete') {
        db_exec('DELETE FROM shop_pricing WHERE id = ? AND shop_id = ?', [(int) ($_POST['id'] ?? 0), $sid]);
        flash('success', 'Price removed.');
    } elseif ($a === 'min') {
        owner_update_settings($sid, ['min_order_charge' => $_POST['min_order_charge'] ?? 0, 'pricing_mode' => $_POST['pricing_mode'] ?? 'auto']);
        flash('success', 'Saved.');
    }
    redirect('owner/pricing.php');
}
$rules = owner_pricing_list($sid);
$opts = [];
foreach (db_all('SELECT id, option_group, label FROM printing_options WHERE is_active = 1 ORDER BY sort_order') as $o) $opts[$o['option_group']][$o['id']] = $o['label'];
$cats = array_column(db_all('SELECT id, name FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order'), 'name', 'id');
$s = shop_settings($sid);
$types = ['print' => 'Printing (per page / sheet)', 'item' => 'Per piece (cards, photos, sets)', 'paper' => 'Paper surcharge', 'finishing' => 'Finishing (binding, lamination…)', 'discount' => 'Quantity discount (%)'];
$edit = isset($_GET['edit']) ? db_row('SELECT * FROM shop_pricing WHERE id = ? AND shop_id = ?', [(int) $_GET['edit'], $sid]) : null;
$r = $edit ?: ['rule_type' => 'print', 'category_id' => null, 'paper_size_id' => null, 'color' => null, 'sides' => null, 'paper_type_id' => null, 'gsm_id' => null, 'finishing_id' => null, 'unit' => 'per_page', 'price' => '', 'min_qty' => 1, 'is_active' => 1];
$sel = fn(array $o, $v, string $any = 'Any') => ['' => $any] + $o;
owner_header('Pricing');
?>
<div class="row g-3">
<div class="col-xl-8">
<div class="p-card"><div class="p-card-head"><i class="bi bi-tags"></i> Your prices (PKR)<span class="ms-auto small text-muted"><?= count($rules) ?> rules</span></div>
<div class="table-responsive"><table class="table p-table"><thead><tr><th>Type</th><th>Applies to</th><th class="text-end">Price</th><th>From qty</th><th></th></tr></thead><tbody>
<?php foreach ($rules as $x): ?><tr class="<?= $x['is_active'] ? '' : 'text-muted' ?>"><td class="small"><?= e(explode(' (', $types[$x['rule_type']])[0]) ?></td><td><?= e($x['applies_to']) ?></td>
<td class="text-end text-nowrap"><strong><?= $x['rule_type'] === 'discount' ? e(rtrim(rtrim(number_format($x['price'], 2), '0'), '.')) . '%' : e(money($x['price'])) ?></strong> <span class="small text-muted"><?= $x['rule_type'] === 'discount' ? 'off' : e(pricing_units()[$x['unit']]) ?></span></td>
<td><?= $x['min_qty'] > 1 ? (int) $x['min_qty'] . '+' : '—' ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="?edit=<?= $x['id'] ?>"><i class="bi bi-pencil"></i></a>
<form method="post" class="d-inline" data-confirm="Remove this price?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
<?php if (!$rules): ?><tr><td colspan="5"><div class="p-empty"><i class="bi bi-tag"></i>No prices yet. Until you add some, every order comes to you as a quote request.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="p-help mb-3"><strong>How prices work.</strong> Leave a field on “Any” to apply it to everything. The highest quantity tier reached wins (e.g. A4 B&amp;W Rs. 10/page, but Rs. 7/page from 100 pages); among same-tier prices the most specific one wins.
For double-sided printing you can price per page (each printed side) or per sheet. Anything you have not priced is sent to you to quote. All amounts are in PKR.</div>
<div class="p-card"><div class="p-card-head"><i class="bi bi-calculator"></i> Test a price</div><div class="p-card-body"><div class="row g-2" id="tester">
    <div class="col-sm-4"><select class="form-select form-select-sm" data-t="category_id"><?php foreach ($cats as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-4"><select class="form-select form-select-sm" data-t="paper_size_id"><?php foreach ($opts['paper_size'] as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-2"><select class="form-select form-select-sm" data-t="color"><option value="bw">B&amp;W</option><option value="color">Colour</option></select></div>
    <div class="col-sm-2"><select class="form-select form-select-sm" data-t="sides"><option value="single">Single</option><option value="double">Double</option></select></div>
    <div class="col-sm-3"><select class="form-select form-select-sm" data-t="paper_type_id"><option value="">Paper: any</option><?php foreach ($opts['paper_type'] as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-3"><select class="form-select form-select-sm" data-t="finishing" multiple size="1" title="Finishing (Ctrl-click for more)"><?php foreach ($opts['finishing'] as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-4 col-sm-2"><input class="form-control form-control-sm" type="number" min="1" value="10" data-t="pages" title="Pages"></div>
    <div class="col-4 col-sm-2"><input class="form-control form-control-sm" type="number" min="1" value="1" data-t="copies" title="Copies"></div>
    <div class="col-4 col-sm-2"><input class="form-control form-control-sm" type="number" min="1" value="1" data-t="quantity" title="Pieces"></div>
</div><div class="mt-3 small" id="testOut">Choose options to see the price customers will get.</div></div></div>
</div>
<div class="col-xl-4">
<div class="p-card"><div class="p-card-head"><?= $edit ? 'Edit price' : 'Add a price' ?><?php if ($edit): ?><a class="ms-auto small" href="<?= e(path('owner/pricing.php')) ?>">New</a><?php endif; ?></div><div class="p-card-body">
<form method="post" id="ruleForm"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <?= f_select('rule_type', 'What is this price for?', $types, $r['rule_type']) ?>
    <div data-show="finishing"><?= f_select('finishing_id', 'Finishing service', $opts['finishing'], $r['finishing_id']) ?></div>
    <?= f_select('category_id', 'Category', $sel($cats, $r['category_id'], 'Any category'), $r['category_id']) ?>
    <div data-show="print item paper finishing discount"><?= f_select('paper_size_id', 'Paper size', $sel($opts['paper_size'], null), $r['paper_size_id']) ?></div>
    <div class="row" data-show="print discount"><div class="col-6"><?= f_select('color', 'Colour', ['' => 'Any', 'bw' => 'Black & White', 'color' => 'Colour'], $r['color']) ?></div><div class="col-6"><?= f_select('sides', 'Sides', ['' => 'Any', 'single' => 'Single', 'double' => 'Double'], $r['sides']) ?></div></div>
    <div class="row" data-show="print item paper"><div class="col-6"><?= f_select('paper_type_id', 'Paper type', $sel($opts['paper_type'], null), $r['paper_type_id']) ?></div><div class="col-6"><?= f_select('gsm_id', 'GSM', $sel($opts['gsm'], null), $r['gsm_id']) ?></div></div>
    <div class="row"><div class="col-7"><?= f_input('price', 'Price', $r['price'], 'number', ['min' => 0, 'step' => '0.5', 'required' => true]) ?></div>
        <div class="col-5" data-show="print item paper finishing"><?= f_select('unit', 'Per', array_diff_key(pricing_units(), ['percent' => 1]), $r['unit']) ?></div></div>
    <?= f_input('min_qty', 'Applies from quantity', $r['min_qty'], 'number', ['min' => 1], 'Pages × copies for documents, pieces for cards & photos. Use for bulk rates and discounts.') ?>
    <?= f_switch('is_active', 'Active', (int) $r['is_active']) ?>
    <button class="btn btn-primary w-100"><?= $edit ? 'Update price' : 'Add price' ?></button>
</form></div></div>
<div class="p-card"><div class="p-card-head">Order rules</div><div class="p-card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="min">
    <?= f_select('pricing_mode', 'Pricing mode', ['auto' => 'Automatic — show my prices to customers', 'quote' => 'Quote — I review every order and send a price'], $s['pricing_mode']) ?>
    <?= f_input('min_order_charge', 'Minimum order charge (PKR)', (float) $s['min_order_charge'], 'number', ['min' => 0, 'step' => 1]) ?>
    <button class="btn btn-outline-primary w-100">Save</button></form></div></div>
</div></div>
<script>
(function () {
    var f = document.getElementById('ruleForm'), t = document.getElementById('f_rule_type');
    function vis() { f.querySelectorAll('[data-show]').forEach(function (el) { el.style.display = el.dataset.show.split(' ').indexOf(t.value) >= 0 ? '' : 'none'; }); }
    t.addEventListener('change', vis); vis();
    var box = document.getElementById('tester'), out = document.getElementById('testOut'), timer;
    function test() {
        var body = {};
        box.querySelectorAll('[data-t]').forEach(function (el) { body[el.dataset.t] = el.multiple ? Array.from(el.selectedOptions).map(function (o) { return o.value; }) : el.value; });
        fetch(<?= json_encode(path('api/v1/owner/pricing/test')) ?>, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body), credentials: 'same-origin' })
            .then(function (r) { return r.json(); }).then(function (r) {
                if (!r.status) { out.textContent = r.message; return; }
                var it = r.data.items[0], h = '<table class="table table-sm mb-1">';
                it.lines.forEach(function (l) { h += '<tr><td>' + l.label.replace(/</g, '&lt;') + '</td><td class="text-end">Rs. ' + Math.round(l.amount).toLocaleString('en-US') + '</td></tr>'; });
                if (r.data.minimum_adjustment) { h += '<tr><td>Minimum order charge</td><td class="text-end">Rs. ' + Math.round(r.data.minimum_adjustment) + '</td></tr>'; }
                h += '<tr class="fw-bold"><td>Total</td><td class="text-end">Rs. ' + Math.round(r.data.total).toLocaleString('en-US') + '</td></tr></table>';
                if (!r.data.priced) { h += '<div class="text-warning"><i class="bi bi-exclamation-triangle"></i> Not priced: ' + it.missing.join(', ') + ' — customers will get a quote request.</div>'; }
                out.innerHTML = h;
            });
    }
    box.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(test, 200); }); box.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(test, 400); });
    test();
})();
</script>
<?php owner_footer();
