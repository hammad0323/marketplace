<?php
require __DIR__ . '/includes/init.php';
admin_require('inventory');
if (is_post()) {
    csrf_check();
    $pid = input_int('product_id'); $vid = input_int('variation_id');
    $mode = input('mode'); $qty = input_int('qty'); $reason = mb_substr(input('reason'), 0, 255);
    $type = array_key_exists(input('type'), movement_types()) ? input('type') : 'adjustment';
    if (!db_val('SELECT id FROM products WHERE id = ?', [$pid])) json_error('Product not found.');
    if ($reason === '') json_error('Please enter a reason.');
    try {
        if ($mode === 'set') $new = stock_set($pid, $vid, $qty, $reason, $type);
        else $new = stock_change($pid, $vid, $mode === 'remove' ? -abs($qty) : abs($qty), $type, ['reason' => $reason, 'ref_type' => 'manual'], false);
    } catch (RuntimeException $e) {
        json_error($e->getMessage());
    }
    json_out(['ok' => true, 'stock' => $new, 'message' => 'Stock updated. New quantity: ' . $new]);
}
$q = input('q'); $filter = input('filter'); $cat = input_int('cat');
$where = ["p.deleted_at IS NULL", "p.status <> 'archived'", "((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0))"];
$params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)'; $like = '%' . db_like($q) . '%'; array_push($params, $like, $like, $like, $like, $like); }
if ($filter === 'low') $where[] = 'p.track_stock = 1 AND i.quantity <= p.low_stock_threshold AND i.quantity > 0';
if ($filter === 'out') $where[] = 'p.track_stock = 1 AND i.quantity <= 0';
if ($cat) { $ids = category_descendant_ids($cat, false); $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))'; $params = array_merge($params, $ids); }
$w = implode(' AND ', $where);
$from = "FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variations v ON v.id = i.variation_id AND v.product_id = p.id WHERE $w";
$total = (int) db_val("SELECT COUNT(*) $from", $params);
$pg = paginate($total, 40);
$rows = db_all("SELECT i.*, p.name, p.sku, p.low_stock_threshold, p.track_stock, p.cost_price AS pcost, p.price AS pprice, v.name AS vname, v.sku AS vsku, v.cost_price AS vcost,
    (SELECT MAX(created_at) FROM inventory_movements m WHERE m.product_id = i.product_id AND m.variation_id = i.variation_id) AS last_move
    $from ORDER BY i.quantity ASC, p.name LIMIT 40 OFFSET {$pg['offset']}", $params);
$val = db_row("SELECT SUM(GREATEST(i.quantity,0) * COALESCE(v.cost_price, p.cost_price)) AS cost_value,
    SUM(GREATEST(i.quantity,0) * COALESCE(CASE WHEN v.sale_price > 0 THEN v.sale_price ELSE v.price END, CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END)) AS retail_value,
    SUM(p.track_stock = 1 AND i.quantity <= 0) AS out_count, SUM(p.track_stock = 1 AND i.quantity > 0 AND i.quantity <= p.low_stock_threshold) AS low_count
    FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variations v ON v.id = i.variation_id
    WHERE p.deleted_at IS NULL AND p.status <> 'archived' AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0))");
$catOpts = []; foreach (category_options() as $o) $catOpts[$o['id']] = $o['label'];
admin_header('Stock', 'inventory');
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="kpi"><i class="bi bi-cash-coin"></i><div><small>Inventory value (cost)</small><strong><?= money($val['cost_value']) ?></strong></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi"><i class="bi bi-tags"></i><div><small>Retail value</small><strong><?= money($val['retail_value']) ?></strong></div></div></div>
  <div class="col-6 col-md-3"><a href="?filter=low" class="kpi text-decoration-none text-dark"><i class="bi bi-exclamation-triangle text-warning"></i><div><small>Low stock</small><strong><?= (int) $val['low_count'] ?></strong></div></a></div>
  <div class="col-6 col-md-3"><a href="?filter=out" class="kpi text-decoration-none text-dark"><i class="bi bi-x-octagon text-danger"></i><div><small>Out of stock</small><strong><?= (int) $val['out_count'] ?></strong></div></a></div>
</div>
<div class="d-flex flex-wrap justify-content-between gap-2">
  <form class="a-filter"><input type="search" name="q" class="form-control form-control-sm" placeholder="Product, SKU or barcode" value="<?= e($q) ?>">
    <?= a_sel('filter', ['low' => 'Low stock', 'out' => 'Out of stock'], $filter, 'All stock') ?><?= a_sel('cat', $catOpts, $cat, 'All categories') ?><button class="btn btn-sm btn-light">Filter</button></form>
  <div><a href="<?= aurl('purchases.php?new=1') ?>" class="btn btn-sm btn-brand"><i class="bi bi-plus-lg me-1"></i>Purchase entry (stock in)</a> <a href="<?= aurl('reports.php?tab=inventory&export=1') ?>" class="btn btn-sm btn-outline-secondary">Export valuation</a></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Stock</th><th class="text-end">Cost</th><th class="text-end">Value</th><th>Last movement</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $cost = $r['vname'] !== null ? $r['vcost'] : $r['pcost']; ?>
  <tr><td><strong><?= e($r['name']) ?></strong><?= $r['vname'] ? ' <span class="text-muted">– ' . e($r['vname']) . '</span>' : '' ?></td><td class="small"><?= e($r['vsku'] ?: $r['sku']) ?></td>
    <td class="text-end"><span class="badge fs-6 <?= !$r['track_stock'] ? 'bg-light text-muted' : ($r['quantity'] <= 0 ? 'bg-danger' : ($r['quantity'] <= $r['low_stock_threshold'] ? 'bg-warning text-dark' : 'bg-light text-dark border')) ?>"><?= (int) $r['quantity'] ?></span></td>
    <td class="text-end small"><?= money($cost) ?></td><td class="text-end small"><?= money(max(0, $r['quantity']) * $cost) ?></td>
    <td class="small text-muted"><?= $r['last_move'] ? time_ago($r['last_move']) : '—' ?></td>
    <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-brand js-adjust" data-pid="<?= (int) $r['product_id'] ?>" data-vid="<?= (int) $r['variation_id'] ?>" data-name="<?= e($r['name'] . ($r['vname'] ? ' – ' . $r['vname'] : '')) ?>" data-qty="<?= (int) $r['quantity'] ?>">Adjust</button>
      <a class="btn btn-sm btn-light" href="<?= aurl('movements.php?product=' . $r['product_id']) ?>" title="History"><i class="bi bi-clock-history"></i></a></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted p-4">No items.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= admin_pagination($pg) ?></div>

<div class="modal fade" id="adjModal" tabindex="-1" aria-labelledby="adjTitle"><div class="modal-dialog"><form class="modal-content" id="adjForm">
  <div class="modal-header"><h2 class="modal-title h5" id="adjTitle">Adjust stock</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <p class="fw-semibold js-adj-name"></p><p class="small">Current stock: <strong class="js-adj-cur"></strong></p>
    <input type="hidden" name="product_id"><input type="hidden" name="variation_id">
    <div class="btn-group w-100 mb-3" role="group">
      <input type="radio" class="btn-check" name="mode" value="add" id="mAdd" checked><label class="btn btn-outline-success" for="mAdd">+ Add</label>
      <input type="radio" class="btn-check" name="mode" value="remove" id="mRem"><label class="btn btn-outline-danger" for="mRem">− Remove</label>
      <input type="radio" class="btn-check" name="mode" value="set" id="mSet"><label class="btn btn-outline-secondary" for="mSet">Set exact</label>
    </div>
    <?= af_text('qty', 'Quantity', '', ['type' => 'number', 'min' => 0, 'required' => true]) ?>
    <?= af_select('type', 'Type', ['adjustment' => 'Manual adjustment / stock count', 'damage' => 'Damaged / expired', 'return' => 'Customer return', 'purchase' => 'Received stock (quick)'], 'adjustment') ?>
    <?= af_text('reason', 'Reason *', '', ['required' => true, 'maxlength' => 255, 'placeholder' => 'e.g. Monthly stock count']) ?>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Save</button></div>
</form></div></div>
<?php admin_footer(); ?>
<script>
var adj = new bootstrap.Modal('#adjModal');
$('.js-adjust').on('click', function () { var d = $(this).data(); var f = $('#adjForm')[0]; f.reset(); f.product_id.value = d.pid; f.variation_id.value = d.vid; $('.js-adj-name').text(d.name); $('.js-adj-cur').text(d.qty); adj.show(); });
$('#adjForm').on('submit', function (e) { e.preventDefault(); FSA.post('inventory.php', $(this).serialize()).done(function (d) { FSA.toast(d.message); setTimeout(function () { location.reload(); }, 500); }); });
</script>
