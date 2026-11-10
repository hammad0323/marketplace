<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/product-lib.php';
admin_require('products');

if (is_post()) {
    csrf_check();
    $act = input('act');
    $ids = array_values(array_filter(array_map('intval', input_arr('ids'))));
    if ($act === 'quick_add') {
        $d = ['name' => input('name'), 'sku' => input('sku'), 'barcode' => input('barcode'), 'price' => input('price'), 'sale_price' => input('sale_price'), 'cost_price' => input('cost_price'),
            'category_ids' => [input_int('category_id')], 'brand_id' => input_int('brand_id'), 'unit' => input('unit'), 'track_stock' => 1, 'status' => 'active',
            'is_rashan_item' => input_int('is_rashan_item'), 'low_stock_threshold' => 5];
        $r = product_save_core(0, $d);
        if (is_string($r)) json_error($r);
        if (input_int('stock') > 0) stock_change($r, 0, input_int('stock'), 'opening', ['reason' => 'Opening stock (quick add)', 'ref_type' => 'manual']); else stock_ensure_row($r, 0);
        $files = upload_files_list('image');
        if ($files) {
            $up = upload_image($files[0], 'products');
            if ($up['ok']) db_insert('INSERT INTO product_images (product_id, image, thumb, alt_text, is_primary) VALUES (?, ?, ?, ?, 1)', [$r, $up['path'], $up['thumb'], input('name')]);
        }
        product_rebuild_search($r);
        catalog_changed();
        json_out(['ok' => true, 'id' => $r, 'message' => 'Product added.', 'edit_url' => aurl('product-edit.php?id=' . $r)]);
    }
    if ($act === 'bulk_save') {
        // inline bulk price/stock editing
        $n = 0;
        foreach (input_arr('row') as $pid => $r) {
            $pid = (int) $pid;
            $p = db_row('SELECT * FROM products WHERE id = ?', [$pid]);
            if (!$p) continue;
            $price = round(max(0, (float) ($r['price'] ?? $p['price'])), 2);
            $sale = ($r['sale_price'] ?? '') !== '' ? round((float) $r['sale_price'], 2) : null;
            if ($sale !== null && ($sale <= 0 || $sale >= $price)) $sale = null;
            db_exec('UPDATE products SET price = ?, sale_price = ?, cost_price = ? WHERE id = ?', [$price, $sale, round(max(0, (float) ($r['cost_price'] ?? $p['cost_price'])), 2), $pid]);
            if (!$p['has_variations'] && isset($r['stock']) && $r['stock'] !== '' && (int) $r['stock'] !== stock_get($pid, 0)) {
                stock_set($pid, 0, (int) $r['stock'], 'Bulk stock update');
            }
            $n++;
        }
        flash('success', $n . ' products updated.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'admin/products.php');
    }
    if ($ids) {
        $in = in_placeholders($ids);
        $map = [
            'activate' => "status = 'active'", 'deactivate' => "status = 'inactive'", 'archive' => "status = 'archived'",
            'feature' => 'is_featured = 1', 'unfeature' => 'is_featured = 0', 'bestseller' => 'is_bestseller = 1', 'unbestseller' => 'is_bestseller = 0',
            'rashan' => 'is_rashan_item = 1', 'unrashan' => 'is_rashan_item = 0',
        ];
        if (isset($map[$act])) {
            db_exec("UPDATE products SET {$map[$act]} WHERE id IN ($in)", $ids);
            flash('success', count($ids) . ' products updated.');
        } elseif ($act === 'delete') {
            // soft delete; orders keep their snapshot. Free SKU/slug for reuse.
            db_exec("UPDATE products SET deleted_at = NOW(), status = 'archived', sku = CONCAT(sku, '-DEL', id), slug = CONCAT(slug, '-deleted-', id), barcode = NULL WHERE id IN ($in)", $ids);
            flash('success', count($ids) . ' products deleted.');
        } elseif ($act === 'price_percent') {
            $pct = input_float('percent', 0.0);
            if ($pct != 0) {
                db_exec("UPDATE products SET price = ROUND(price * (1 + ? / 100), 0), sale_price = CASE WHEN sale_price IS NULL THEN NULL ELSE ROUND(sale_price * (1 + ? / 100), 0) END WHERE id IN ($in)", array_merge([$pct, $pct], $ids));
                db_exec("UPDATE product_variations SET price = ROUND(price * (1 + ? / 100), 0), sale_price = CASE WHEN sale_price IS NULL THEN NULL ELSE ROUND(sale_price * (1 + ? / 100), 0) END WHERE product_id IN ($in)", array_merge([$pct, $pct], $ids));
                flash('success', 'Prices changed by ' . $pct . '% for ' . count($ids) . ' products.');
            }
        } elseif ($act === 'set_category' && ($cid = input_int('category_id'))) {
            foreach ($ids as $pid) { db_exec('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)', [$pid, $cid]); product_rebuild_search($pid); }
            flash('success', 'Category added to ' . count($ids) . ' products.');
        }
        catalog_changed();
    } else {
        flash('warning', 'Select at least one product.');
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/products.php');
}

// ---------- Listing ----------
$f = ['q' => input('q'), 'cat' => input_int('cat'), 'brand' => input_int('brand'), 'status' => input('status'), 'stock' => input('stock'), 'flag' => input('flag'), 'sort' => input('sort', 'newest')];
$where = ['p.deleted_at IS NULL'];
$params = [];
if ($f['q'] !== '') {
    $like = '%' . db_like($f['q']) . '%';
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.isbn LIKE ? OR p.name_urdu LIKE ? OR p.id IN (SELECT product_id FROM product_variations WHERE sku LIKE ? OR barcode LIKE ?))';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
if ($f['cat']) { $ids = category_descendant_ids($f['cat'], false); $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))'; $params = array_merge($params, $ids); }
if ($f['brand']) { $where[] = 'p.brand_id = ?'; $params[] = $f['brand']; }
if (in_array($f['status'], ['active', 'inactive', 'archived'], true)) { $where[] = 'p.status = ?'; $params[] = $f['status']; }
$stockExpr = 'COALESCE((SELECT SUM(i.quantity) FROM inventory i WHERE i.product_id = p.id AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0))), 0)';
if ($f['stock'] === 'out') $where[] = "p.track_stock = 1 AND $stockExpr <= 0";
if ($f['stock'] === 'low') $where[] = "p.track_stock = 1 AND $stockExpr <= p.low_stock_threshold";
if ($f['flag'] === 'featured') $where[] = 'p.is_featured = 1';
if ($f['flag'] === 'bestseller') $where[] = 'p.is_bestseller = 1';
if ($f['flag'] === 'rashan') $where[] = 'p.is_rashan_item = 1';
if ($f['flag'] === 'noimage') $where[] = 'NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id)';
$sorts = ['newest' => 'p.id DESC', 'name' => 'p.name ASC', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'stock' => 'stock ASC', 'sales' => 'p.sales_count DESC'];
$order = $sorts[$f['sort']] ?? 'p.id DESC';
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM products p WHERE $w", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT p.*, b.name AS brand_name, $stockExpr AS stock,
    (SELECT thumb FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order LIMIT 1) AS thumb,
    (SELECT name FROM categories WHERE id = p.primary_category_id) AS cat_name
    FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE $w ORDER BY $order LIMIT 30 OFFSET {$pg['offset']}", $params);
$catOpts = [];
foreach (category_options() as $o) $catOpts[$o['id']] = $o['label'];
$brandOpts = [];
foreach (db_all('SELECT id, name FROM brands WHERE deleted_at IS NULL ORDER BY name') as $b) $brandOpts[$b['id']] = $b['name'];
$bulkEdit = input('mode') === 'bulk';

admin_header('Products', 'products');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="a-filter mb-0" method="get">
    <input type="search" name="q" class="form-control form-control-sm" placeholder="Name, SKU, barcode, ISBN" value="<?= e($f['q']) ?>" style="min-width:220px">
    <?= a_sel('cat', $catOpts, $f['cat'], 'All categories') ?>
    <?= a_sel('brand', $brandOpts, $f['brand'], 'All brands') ?>
    <?= a_sel('status', ['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'], $f['status'], 'Any status') ?>
    <?= a_sel('stock', ['low' => 'Low stock', 'out' => 'Out of stock'], $f['stock'], 'Any stock') ?>
    <?= a_sel('flag', ['featured' => 'Featured', 'bestseller' => 'Best seller', 'rashan' => 'Rashan item', 'noimage' => 'Without image'], $f['flag'], 'Any flag') ?>
    <?= a_sel('sort', ['name' => 'Name', 'price_asc' => 'Price ↑', 'price_desc' => 'Price ↓', 'stock' => 'Stock ↑', 'sales' => 'Best selling'], $f['sort'] === 'newest' ? '' : $f['sort'], 'Newest') ?>
    <?php if ($bulkEdit): ?><input type="hidden" name="mode" value="bulk"><?php endif; ?>
    <button class="btn btn-sm btn-light">Filter</button>
  </form>
  <div class="d-flex gap-2">
    <a href="<?= e(url_with(['mode' => $bulkEdit ? null : 'bulk'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square me-1"></i><?= $bulkEdit ? 'Exit bulk edit' : 'Bulk edit prices/stock' ?></a>
    <button class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#quickAdd"><i class="bi bi-lightning me-1"></i>Quick add</button>
    <a href="<?= aurl('product-edit.php') ?>" class="btn btn-sm btn-brand"><i class="bi bi-plus-lg me-1"></i>Add product</a>
  </div>
</div>

<form method="post" id="listForm">
  <?= csrf_field() ?>
  <?php if (!$bulkEdit): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <select name="act" class="form-select form-select-sm w-auto" id="bulkAct">
      <option value="">Bulk action…</option>
      <option value="activate">Activate</option><option value="deactivate">Deactivate</option><option value="archive">Archive</option>
      <option value="feature">Mark featured</option><option value="unfeature">Remove featured</option>
      <option value="bestseller">Mark best seller</option><option value="unbestseller">Remove best seller</option>
      <option value="rashan">Add to Rashan builder</option><option value="unrashan">Remove from Rashan builder</option>
      <option value="price_percent">Change price by %</option><option value="set_category">Add to category</option>
      <option value="delete">Delete</option>
    </select>
    <input type="number" step="0.1" name="percent" class="form-control form-control-sm w-auto d-none js-bulk-pct" placeholder="% e.g. 10 or -5">
    <select name="category_id" class="form-select form-select-sm w-auto d-none js-bulk-cat"><?php foreach ($catOpts as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-dark" onclick="var a=document.getElementById('bulkAct').value; if(!a){return false;} if(a==='delete'){return confirm('Delete selected products?');}">Apply</button>
    <span class="small text-muted ms-auto"><?= $total ?> products</span>
  </div>
  <?php else: ?>
  <input type="hidden" name="act" value="bulk_save">
  <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center">Edit prices, cost and stock directly, then save. Stock changes are logged as adjustments. <button class="btn btn-sm btn-brand">Save all changes</button></div>
  <?php endif; ?>
  <div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><?php if (!$bulkEdit): ?><th><input type="checkbox" class="form-check-input js-check-all" data-target=".js-row-check" aria-label="Select all"></th><?php endif; ?><th></th><th>Product</th><th>Category</th><th>Price</th><?php if ($bulkEdit): ?><th>Sale</th><th>Cost</th><?php endif; ?><th>Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $low = $p['track_stock'] && $p['stock'] <= $p['low_stock_threshold']; ?>
    <tr>
      <?php if (!$bulkEdit): ?><td><input type="checkbox" class="form-check-input js-row-check" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Select"></td><?php endif; ?>
      <td><img src="<?= e(upload_url($p['thumb'])) ?>" class="a-thumb" alt="" loading="lazy"></td>
      <td><a href="<?= aurl('product-edit.php?id=' . $p['id']) ?>" class="fw-semibold text-dark"><?= e($p['name']) ?></a>
        <div class="small text-muted">SKU <?= e($p['sku']) ?><?= $p['isbn'] ? ' · ISBN ' . e($p['isbn']) : '' ?><?= $p['brand_name'] ? ' · ' . e($p['brand_name']) : '' ?></div>
        <div><?= $p['is_featured'] ? '<span class="badge bg-warning text-dark">Featured</span> ' : '' ?><?= $p['is_bestseller'] ? '<span class="badge bg-info">Best seller</span> ' : '' ?><?= $p['is_rashan_item'] ? '<span class="badge bg-success">Rashan</span> ' : '' ?><?= $p['has_variations'] ? '<span class="badge bg-light text-dark border">Variations</span>' : '' ?></div></td>
      <td class="small"><?= e($p['cat_name']) ?></td>
      <?php if ($bulkEdit): ?>
        <td><input type="number" step="0.01" min="0" name="row[<?= (int) $p['id'] ?>][price]" class="form-control form-control-sm" style="width:100px" value="<?= e($p['price']) ?>"></td>
        <td><input type="number" step="0.01" min="0" name="row[<?= (int) $p['id'] ?>][sale_price]" class="form-control form-control-sm" style="width:100px" value="<?= e($p['sale_price']) ?>"></td>
        <td><input type="number" step="0.01" min="0" name="row[<?= (int) $p['id'] ?>][cost_price]" class="form-control form-control-sm" style="width:100px" value="<?= e($p['cost_price']) ?>"></td>
        <td><?php if ($p['has_variations']): ?><span class="small text-muted"><?= (int) $p['stock'] ?> (edit variations)</span><?php else: ?><input type="number" step="1" name="row[<?= (int) $p['id'] ?>][stock]" class="form-control form-control-sm" style="width:90px" value="<?= (int) $p['stock'] ?>"><?php endif; ?></td>
      <?php else: ?>
        <td class="text-nowrap"><?= money(effective_price($p['price'], $p['sale_price'])) ?><?= discount_percent($p['price'], $p['sale_price']) ? '<br><del class="small text-muted">' . money($p['price']) . '</del>' : '' ?></td>
        <td><?= $p['track_stock'] ? '<span class="badge ' . ($p['stock'] <= 0 ? 'bg-danger' : ($low ? 'bg-warning text-dark' : 'bg-light text-dark border')) . '">' . (int) $p['stock'] . '</span>' : '<span class="text-muted small">Not tracked</span>' ?></td>
      <?php endif; ?>
      <td><?= ['active' => '<span class="badge bg-success">Active</span>', 'inactive' => '<span class="badge bg-secondary">Inactive</span>', 'archived' => '<span class="badge bg-dark">Archived</span>'][$p['status']] ?></td>
      <td class="text-end text-nowrap"><a href="<?= e(product_url($p)) ?>" target="_blank" class="btn btn-sm btn-light" title="View"><i class="bi bi-eye"></i></a> <a href="<?= aurl('product-edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <a href="<?= aurl('product-edit.php?duplicate=' . $p['id']) ?>" class="btn btn-sm btn-light" title="Duplicate"><i class="bi bi-copy"></i></a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted p-4">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div></div>
  <?php if ($bulkEdit && $rows): ?><div class="text-end mt-2"><button class="btn btn-brand">Save all changes</button></div><?php endif; ?>
</form>
<div class="mt-3"><?= admin_pagination($pg) ?></div>

<!-- Quick add -->
<div class="modal fade" id="quickAdd" tabindex="-1" aria-labelledby="qaTitle">
  <div class="modal-dialog modal-lg"><form class="modal-content" id="qaForm" enctype="multipart/form-data">
    <div class="modal-header"><h2 class="modal-title h5" id="qaTitle">Quick add product</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="act" value="quick_add">
      <div class="row">
        <div class="col-md-8"><?= af_text('name', 'Product name *', '', ['required' => true]) ?></div>
        <div class="col-md-4"><?= af_select('unit', 'Unit', units_list(), 'piece') ?></div>
        <div class="col-md-6"><?= af_select('category_id', 'Category *', $catOpts, '', ['required' => true]) ?></div>
        <div class="col-md-6"><?= af_select('brand_id', 'Brand', ['' => '—'] + $brandOpts, '') ?></div>
        <div class="col-md-3"><?= af_text('price', 'Price *', '', ['type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true]) ?></div>
        <div class="col-md-3"><?= af_text('sale_price', 'Sale price', '', ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
        <div class="col-md-3"><?= af_text('cost_price', 'Cost', '', ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
        <div class="col-md-3"><?= af_text('stock', 'Opening stock', '0', ['type' => 'number', 'min' => 0]) ?></div>
        <div class="col-md-6"><?= af_text('sku', 'SKU (auto if empty)', '') ?></div>
        <div class="col-md-6"><?= af_text('barcode', 'Barcode (scan)', '') ?></div>
        <div class="col-md-8"><label class="form-label" for="qaImg">Image</label><input type="file" id="qaImg" name="image" class="form-control js-img-input" data-preview="#qaPrev" accept="image/*"></div>
        <div class="col-md-4"><img id="qaPrev" class="a-img-preview" src="<?= url('assets/images/placeholder.svg') ?>" alt=""></div>
        <div class="col-12 mt-2"><?= af_check('is_rashan_item', 'Show in Monthly Rashan builder', false) ?></div>
      </div>
    </div>
    <div class="modal-footer"><span class="small text-muted me-auto">Need variations, gallery or SEO? Use the full editor after saving.</span><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save product</button></div>
  </form></div>
</div>
<?php admin_footer(); ?>
<script>
$('#bulkAct').on('change', function () { $('.js-bulk-pct').toggleClass('d-none', this.value !== 'price_percent'); $('.js-bulk-cat').toggleClass('d-none', this.value !== 'set_category'); });
$('#qaForm').on('submit', function (e) {
  e.preventDefault();
  var fd = new FormData(this);
  $.ajax({ url: FSA.admin + 'products.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
    .done(function (d) { FSA.toast(d.message); setTimeout(function () { location.reload(); }, 600); })
    .fail(function (x) { FSA.toast((x.responseJSON && x.responseJSON.error) || 'Could not save', 'error'); });
});
</script>
