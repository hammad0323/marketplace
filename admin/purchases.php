<?php
/** Purchase entries (stock in) from suppliers. Receiving adds stock exactly once. */
require __DIR__ . '/includes/init.php';
admin_require('inventory');

if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') {
        $p = db_row('SELECT * FROM purchases WHERE id = ?', [$id]);
        if ($p && $p['status'] === 'draft') { db_exec('DELETE FROM purchases WHERE id = ?', [$id]); flash('success', 'Draft deleted.'); }
        else flash('danger', 'Received purchases cannot be deleted (stock already added). Use a stock adjustment instead.');
        redirect('admin/purchases.php');
    }
    if ($id && db_val('SELECT status FROM purchases WHERE id = ?', [$id]) === 'received') { flash('danger', 'This purchase is already received and locked.'); redirect('admin/purchases.php?view=' . $id); }
    $lines = [];
    foreach (input_arr('items') as $it) {
        $pid = (int) ($it['product_id'] ?? 0); $vid = (int) ($it['variation_id'] ?? 0); $qty = (int) ($it['quantity'] ?? 0);
        if ($pid && $qty > 0 && db_val('SELECT id FROM products WHERE id = ?', [$pid])) $lines[] = [$pid, $vid, $qty, round(max(0, (float) ($it['unit_cost'] ?? 0)), 2)];
    }
    if (!$lines) { flash('danger', 'Add at least one product with quantity.'); redirect('admin/purchases.php?new=1'); }
    $total = array_sum(array_map(function ($l) { return $l[2] * $l[3]; }, $lines));
    $data = ['supplier_id' => input_int('supplier_id') ?: null, 'reference_no' => input('reference_no') ?: null, 'purchase_date' => input('purchase_date') ?: date('Y-m-d'),
        'total_amount' => $total, 'notes' => input('notes') ?: null, 'status' => 'draft', 'admin_id' => admin_id()];
    $id = db_tx(function () use ($id, $data, $lines) {
        if ($id) { db_update_row('purchases', $data, 'id = ?', [$id]); db_exec('DELETE FROM purchase_items WHERE purchase_id = ?', [$id]); }
        else $id = db_insert_row('purchases', $data);
        foreach ($lines as [$pid, $vid, $qty, $cost]) db_exec('INSERT INTO purchase_items (purchase_id, product_id, variation_id, quantity, unit_cost, line_total) VALUES (?, ?, ?, ?, ?, ?)', [$id, $pid, $vid, $qty, $cost, $qty * $cost]);
        return $id;
    });
    if (input('receive') === '1') {
        purchase_receive($id);
        flash('success', 'Purchase received. Stock has been added.');
    } else {
        flash('success', 'Purchase saved as draft. Stock will be added when you mark it received.');
    }
    redirect('admin/purchases.php?view=' . $id);
}

$suppliers = ['' => '— Select supplier —'];
foreach (db_all('SELECT id, name FROM suppliers WHERE deleted_at IS NULL ORDER BY name') as $s) $suppliers[$s['id']] = $s['name'];
$view = input_int('view');
$editId = input_int('edit');

if (input('new') === '1' || $editId):
    $pur = $editId ? db_row("SELECT * FROM purchases WHERE id = ? AND status = 'draft'", [$editId]) : null;
    $items = $pur ? db_all('SELECT pi.*, p.name, v.name AS vname FROM purchase_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN product_variations v ON v.id = pi.variation_id WHERE pi.purchase_id = ?', [$pur['id']]) : [];
    admin_header($pur ? 'Edit purchase #' . $pur['id'] : 'New purchase entry', 'purchases');
?>
<form method="post" id="purForm">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($pur['id'] ?? 0) ?>">
  <div class="card mb-3"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= af_select('supplier_id', 'Supplier', $suppliers, $pur['supplier_id'] ?? '', [], '<a href="' . aurl('suppliers.php') . '">Manage suppliers</a>') ?></div>
    <div class="col-md-3"><?= af_text('reference_no', 'Supplier invoice #', $pur['reference_no'] ?? '') ?></div>
    <div class="col-md-3"><?= af_text('purchase_date', 'Date', $pur['purchase_date'] ?? date('Y-m-d'), ['type' => 'date']) ?></div>
  </div></div></div>
  <div class="card mb-3"><div class="card-header">Products</div><div class="card-body">
    <input type="search" class="form-control js-product-picker" data-results="#purResults" placeholder="Search or scan product to add…" autofocus>
    <div class="list-group mt-1" id="purResults"></div>
    <table class="table align-middle mt-3" id="purTable"><thead><tr><th>Product</th><th style="width:120px">Qty</th><th style="width:150px">Unit cost</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
      <?php foreach ($items as $i => $it): ?>
      <tr><td><?= e($it['name'] . ($it['vname'] ? ' – ' . $it['vname'] : '')) ?><input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= (int) $it['product_id'] ?>"><input type="hidden" name="items[<?= $i ?>][variation_id]" value="<?= (int) $it['variation_id'] ?>"></td>
        <td><input type="number" min="1" class="form-control form-control-sm js-q" name="items[<?= $i ?>][quantity]" value="<?= (int) $it['quantity'] ?>"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-c" name="items[<?= $i ?>][unit_cost]" value="<?= e($it['unit_cost']) ?>"></td><td class="text-end js-t"></td><td><button type="button" class="btn btn-sm btn-light text-danger js-rm"><i class="bi bi-x"></i></button></td></tr>
      <?php endforeach; ?>
    </tbody><tfoot><tr><th colspan="3" class="text-end">Grand total</th><th class="text-end js-grand"></th><th></th></tr></tfoot></table>
  </div></div>
  <?= af_textarea('notes', 'Notes', $pur['notes'] ?? '', ['rows' => 2]) ?>
  <div class="d-flex gap-2"><button class="btn btn-outline-brand" name="receive" value="0">Save as draft</button><button class="btn btn-brand" name="receive" value="1" onclick="return confirm('Mark as received and add stock now?')"><i class="bi bi-box-arrow-in-down me-1"></i>Save &amp; receive stock</button></div>
</form>
<?php admin_footer(); ?>
<script>
var pi = <?= count($items) ?>;
function calc() { var g = 0; $('#purTable tbody tr').each(function () { var t = (+$(this).find('.js-q').val() || 0) * (+$(this).find('.js-c').val() || 0); g += t; $(this).find('.js-t').text(FSA.money(t)); }); $('.js-grand').text(FSA.money(g)); }
$(document).on('click', '#purResults .js-pick', function () {
  var d = $(this).data(), i = pi++;
  $('#purTable tbody').append('<tr><td>' + FSA.esc(d.name) + '<input type="hidden" name="items[' + i + '][product_id]" value="' + d.id + '"><input type="hidden" name="items[' + i + '][variation_id]" value="' + d.vid + '"></td>'
    + '<td><input type="number" min="1" class="form-control form-control-sm js-q" name="items[' + i + '][quantity]" value="1"></td>'
    + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-c" name="items[' + i + '][unit_cost]" value="' + (d.cost || 0) + '"></td><td class="text-end js-t"></td><td><button type="button" class="btn btn-sm btn-light text-danger js-rm"><i class="bi bi-x"></i></button></td></tr>');
  $('#purResults').empty(); $('.js-product-picker').val('').focus(); calc();
});
$(document).on('input', '.js-q, .js-c', calc);
$(document).on('click', '.js-rm', function () { $(this).closest('tr').remove(); calc(); });
calc();
</script>
<?php
elseif ($view):
    $pur = db_row('SELECT p.*, s.name AS supplier, a.name AS admin FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN admins a ON a.id = p.admin_id WHERE p.id = ?', [$view]);
    if (!$pur) { flash('danger', 'Purchase not found.'); redirect('admin/purchases.php'); }
    $items = db_all('SELECT pi.*, p.name, v.name AS vname FROM purchase_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN product_variations v ON v.id = pi.variation_id WHERE pi.purchase_id = ?', [$view]);
    admin_header('Purchase #' . $pur['id'], 'purchases');
?>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div><strong><?= e($pur['supplier'] ?: 'No supplier') ?></strong> · Invoice <?= e($pur['reference_no'] ?: '—') ?> · <?= fmt_date($pur['purchase_date']) ?><br><small class="text-muted">Entered by <?= e($pur['admin']) ?></small></div>
    <div><?= $pur['status'] === 'received' ? '<span class="badge bg-success fs-6">Received</span>' : '<span class="badge bg-warning text-dark fs-6">Draft</span>' ?></div>
  </div>
  <table class="table"><thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Total</th></tr></thead><tbody>
  <?php foreach ($items as $it): ?><tr><td><?= e($it['name'] . ($it['vname'] ? ' – ' . $it['vname'] : '')) ?></td><td class="text-end"><?= (int) $it['quantity'] ?></td><td class="text-end"><?= money($it['unit_cost']) ?></td><td class="text-end"><?= money($it['line_total']) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= money($pur['total_amount']) ?></th></tr></tfoot></table>
  <?php if ($pur['notes']): ?><p class="small"><strong>Notes:</strong> <?= e($pur['notes']) ?></p><?php endif; ?>
  <?php if ($pur['status'] === 'draft'): ?>
  <div class="d-flex gap-2"><a href="?edit=<?= (int) $pur['id'] ?>" class="btn btn-outline-secondary">Edit</a>
    <form method="post" action="?" data-confirm="Receive this purchase and add stock?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $pur['id'] ?>">
      <?php foreach ($items as $i => $it): ?><input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= (int) $it['product_id'] ?>"><input type="hidden" name="items[<?= $i ?>][variation_id]" value="<?= (int) $it['variation_id'] ?>"><input type="hidden" name="items[<?= $i ?>][quantity]" value="<?= (int) $it['quantity'] ?>"><input type="hidden" name="items[<?= $i ?>][unit_cost]" value="<?= e($it['unit_cost']) ?>"><?php endforeach; ?>
      <input type="hidden" name="supplier_id" value="<?= (int) $pur['supplier_id'] ?>"><input type="hidden" name="reference_no" value="<?= e($pur['reference_no']) ?>"><input type="hidden" name="purchase_date" value="<?= e($pur['purchase_date']) ?>"><input type="hidden" name="notes" value="<?= e($pur['notes']) ?>">
      <button class="btn btn-brand" name="receive" value="1">Receive stock</button></form>
    <form method="post" data-confirm="Delete this draft?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $pur['id'] ?>"><button class="btn btn-outline-danger">Delete draft</button></form></div>
  <?php endif; ?>
</div></div>
<?php admin_footer();
else:
    $sup = input_int('supplier');
    $where = $sup ? 'WHERE p.supplier_id = ' . $sup : '';
    $pg = paginate((int) db_val("SELECT COUNT(*) FROM purchases p $where"), 30);
    $rows = db_all("SELECT p.*, s.name AS supplier, (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) AS line_count FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id $where ORDER BY p.id DESC LIMIT 30 OFFSET {$pg['offset']}");
    admin_header('Purchases (Stock In)', 'purchases');
?>
<div class="d-flex justify-content-end mb-3"><a href="?new=1" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New purchase entry</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>#</th><th>Date</th><th>Supplier</th><th>Invoice</th><th>Lines</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= (int) $r['id'] ?></td><td><?= fmt_date($r['purchase_date']) ?></td><td><?= e($r['supplier'] ?: '—') ?></td><td><?= e($r['reference_no']) ?></td><td><?= (int) $r['line_count'] ?></td><td class="text-end"><?= money($r['total_amount']) ?></td>
    <td><?= $r['status'] === 'received' ? '<span class="badge bg-success">Received</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?></td><td class="text-end"><a href="?view=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted p-4">No purchases yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
endif;
