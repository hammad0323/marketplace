<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/product-lib.php';
admin_require('products');

$id = input_int('id');
$dupFrom = input_int('duplicate');

if (is_post()) {
    csrf_check();
    $post = $_POST;
    $post['category_ids'] = input_arr('category_ids');
    $r = product_save_core($id, $post);
    if (is_string($r)) {
        flash('danger', $r);
        keep_old($_POST);
        redirect('admin/product-edit.php' . ($id ? '?id=' . $id : ''));
    }
    $pid = $r;
    $errors = [];
    try {
        db_tx(function () use ($pid, &$errors) {
            // ---- Variations ----
            $vars = input_arr('var');
            $keep = [];
            $hasVar = false;
            foreach ($vars as $v) {
                $vname = trim((string) ($v['name'] ?? ''));
                if ($vname === '') continue;
                $vid = (int) ($v['id'] ?? 0);
                if ($vid && !db_val('SELECT id FROM product_variations WHERE id = ? AND product_id = ?', [$vid, $pid])) $vid = 0;
                $vsku = trim((string) ($v['sku'] ?? '')) ?: (db_val('SELECT sku FROM products WHERE id = ?', [$pid]) . '-' . slugify($vname));
                $vbar = trim((string) ($v['barcode'] ?? '')) ?: null;
                if ($err = product_code_conflict($vsku, $vbar, -1, $vid)) { $errors[] = $vname . ': ' . $err; continue; }
                if ($vsku === db_val('SELECT sku FROM products WHERE id = ?', [$pid])) { $errors[] = $vname . ': variation SKU must differ from the product SKU.'; continue; }
                $price = round(max(0, (float) ($v['price'] ?? 0)), 2);
                $sale = ($v['sale_price'] ?? '') !== '' ? round((float) $v['sale_price'], 2) : null;
                if ($sale !== null && ($sale <= 0 || $sale >= $price)) $sale = null;
                $row = ['name' => mb_substr($vname, 0, 120), 'sku' => mb_substr($vsku, 0, 64), 'barcode' => $vbar, 'price' => $price, 'sale_price' => $sale,
                    'cost_price' => round(max(0, (float) ($v['cost_price'] ?? 0)), 2), 'sort_order' => count($keep), 'is_active' => !empty($v['is_active']) ? 1 : 0];
                if ($vid) db_update_row('product_variations', $row, 'id = ?', [$vid]);
                else { $row['product_id'] = $pid; $vid = db_insert_row('product_variations', $row); }
                $keep[] = $vid;
                $hasVar = true;
                // stock for variation
                if (isset($v['stock']) && $v['stock'] !== '' && (int) $v['stock'] !== stock_get($pid, $vid)) {
                    stock_set($pid, $vid, (int) $v['stock'], 'Set from product editor', db_val('SELECT COUNT(*) FROM inventory_movements WHERE product_id = ? AND variation_id = ?', [$pid, $vid]) ? 'adjustment' : 'opening');
                } else {
                    stock_ensure_row($pid, $vid);
                }
            }
            // remove deleted variations (keep if referenced by orders -> deactivate)
            $existing = array_map('intval', db_col('SELECT id FROM product_variations WHERE product_id = ?', [$pid]));
            foreach (array_diff($existing, $keep) as $old) {
                if (db_val('SELECT id FROM order_items WHERE variation_id = ? LIMIT 1', [$old])) db_exec('UPDATE product_variations SET is_active = 0 WHERE id = ?', [$old]);
                else { db_exec('DELETE FROM product_variations WHERE id = ?', [$old]); db_exec('DELETE FROM inventory WHERE product_id = ? AND variation_id = ?', [$pid, $old]); }
            }
            db_exec('UPDATE products SET has_variations = ? WHERE id = ?', [$hasVar ? 1 : 0, $pid]);
            if ($hasVar) {
                $min = db_val('SELECT MIN(price) FROM product_variations WHERE product_id = ? AND is_active = 1', [$pid]);
                if ($min !== null) db_exec('UPDATE products SET price = ?, sale_price = NULL WHERE id = ? AND price = 0', [$min, $pid]);
            } else {
                // simple product stock
                $stock = input('stock');
                if ($stock !== '' && (int) $stock !== stock_get($pid, 0)) {
                    $hasMov = db_val('SELECT COUNT(*) FROM inventory_movements WHERE product_id = ? AND variation_id = 0', [$pid]);
                    stock_set($pid, 0, (int) $stock, input('stock_reason') ?: ($hasMov ? 'Set from product editor' : 'Opening stock'), $hasMov ? 'adjustment' : 'opening');
                } else {
                    stock_ensure_row($pid, 0);
                }
            }
        });
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }

    // ---- Images ----
    foreach (input_arr('img') as $imgId => $im) {
        $imgId = (int) $imgId;
        if (!empty($im['delete'])) {
            $row = db_row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $pid]);
            if ($row) { upload_delete($row['image']); upload_delete($row['thumb']); db_exec('DELETE FROM product_images WHERE id = ?', [$imgId]); }
            continue;
        }
        db_exec('UPDATE product_images SET alt_text = ?, sort_order = ? WHERE id = ? AND product_id = ?', [mb_substr((string) ($im['alt'] ?? ''), 0, 190) ?: null, (int) ($im['sort'] ?? 0), $imgId, $pid]);
    }
    $hasPrimary = (int) db_val('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$pid]);
    foreach (upload_files_list('images') as $i => $file) {
        $up = upload_image($file, 'products');
        if (!$up['ok']) { $errors[] = $file['name'] . ': ' . $up['error']; continue; }
        db_insert('INSERT INTO product_images (product_id, image, thumb, alt_text, sort_order, is_primary) VALUES (?, ?, ?, ?, ?, ?)',
            [$pid, $up['path'], $up['thumb'], input('name'), 100 + $i, (!$hasPrimary && $i === 0) ? 1 : 0]);
    }
    if ($primary = input_int('primary_image')) {
        db_exec('UPDATE product_images SET is_primary = (id = ?) WHERE product_id = ?', [$primary, $pid]);
    }
    if (!db_val('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1', [$pid])) {
        db_exec('UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$pid]);
    }
    // ---- Related products ----
    db_exec('DELETE FROM related_products WHERE product_id = ?', [$pid]);
    foreach (array_unique(array_map('intval', input_arr('related'))) as $rid) {
        if ($rid && $rid !== $pid) db_exec('INSERT IGNORE INTO related_products (product_id, related_id) VALUES (?, ?)', [$pid, $rid]);
    }
    seo_save('product', $pid, input_arr('seo'));
    product_rebuild_search($pid);
    catalog_changed();
    clear_old();
    if ($errors) flash('warning', 'Saved with warnings: ' . implode(' | ', $errors));
    else flash('success', 'Product saved.');
    redirect('admin/product-edit.php?id=' . $pid);
}

$p = $id ? db_row('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
if ($id && !$p) { flash('danger', 'Product not found.'); redirect('admin/products.php'); }
$src = $p ?: ($dupFrom ? db_row('SELECT * FROM products WHERE id = ?', [$dupFrom]) : null);
$defaults = ['name' => '', 'name_urdu' => '', 'slug' => '', 'sku' => '', 'barcode' => '', 'product_code' => '', 'isbn' => '', 'brand_id' => null, 'primary_category_id' => null,
    'short_description' => '', 'description' => '', 'keywords' => '', 'price' => '', 'sale_price' => '', 'cost_price' => '', 'unit' => 'piece', 'unit_value' => '',
    'weight_grams' => '', 'length_cm' => '', 'width_cm' => '', 'height_cm' => '', 'low_stock_threshold' => 5, 'min_order_qty' => 1, 'max_order_qty' => '',
    'track_stock' => 1, 'is_featured' => 0, 'is_bestseller' => 0, 'is_rashan_item' => 0, 'status' => 'active', 'has_variations' => 0];
$v = array_merge($defaults, $src ?: []);
if ($dupFrom && !$p) { $v['name'] .= ' (copy)'; $v['slug'] = ''; $v['sku'] = ''; $v['barcode'] = ''; }
foreach ($defaults as $k => $_) if (isset($_SESSION['_old'][$k])) $v[$k] = $_SESSION['_old'][$k];
$srcId = (int) ($src['id'] ?? 0);
$catIds = $srcId ? product_category_ids($srcId) : array_map('intval', $_SESSION['_old']['category_ids'] ?? []);
$tags = $srcId ? implode(', ', db_col('SELECT tag FROM product_tags WHERE product_id = ?', [$srcId])) : '';
$variations = $srcId ? product_variations($srcId, false) : [];
if ($dupFrom && !$p) foreach ($variations as &$vv) { $vv['id'] = 0; $vv['sku'] = ''; $vv['barcode'] = ''; $vv['stock'] = 0; } unset($vv);
$images = $p ? product_images($id) : [];
$related = $p ? db_all('SELECT p.id, p.name FROM related_products r JOIN products p ON p.id = r.related_id WHERE r.product_id = ?', [$id]) : [];
$stock = $p && !$p['has_variations'] ? stock_get($id, 0) : 0;
$seo = seo_get('product', $id);
$brandOpts = ['' => '— No brand —'];
foreach (db_all('SELECT id, name FROM brands WHERE deleted_at IS NULL ORDER BY name') as $b) $brandOpts[$b['id']] = $b['name'];

admin_header($p ? 'Edit: ' . $p['name'] : 'Add product', 'products');
?>
<form method="post" enctype="multipart/form-data" id="productForm">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header">Basic information</div><div class="card-body"><div class="row">
      <div class="col-md-8"><?= af_text('name', 'Product name *', $v['name'], ['required' => true, 'maxlength' => 220, 'class' => 'form-control js-slug-source', 'data-slug' => '#f_slug']) ?></div>
      <div class="col-md-4"><?= af_text('name_urdu', 'Urdu name', $v['name_urdu'], ['dir' => 'rtl', 'maxlength' => 220]) ?></div>
      <div class="col-md-6"><?= af_text('slug', 'URL slug', $v['slug'], ['class' => 'form-control js-slug', 'maxlength' => 230], $p ? 'URL: ' . e(abs_url('product/' . $v['slug'] . '/')) . ' (old URLs redirect automatically)' : 'Leave empty to generate from the name.') ?></div>
      <div class="col-md-6"><?= af_select('brand_id', 'Brand', $brandOpts, $v['brand_id']) ?></div>
      <div class="col-md-3"><?= af_text('sku', 'SKU', $v['sku'], ['maxlength' => 64, 'placeholder' => 'Auto']) ?></div>
      <div class="col-md-3"><?= af_text('barcode', 'Barcode', $v['barcode'], ['maxlength' => 64, 'placeholder' => 'Scan here']) ?></div>
      <div class="col-md-3"><?= af_text('product_code', 'Product code', $v['product_code'], ['maxlength' => 64]) ?></div>
      <div class="col-md-3"><?= af_text('isbn', 'ISBN (books)', $v['isbn'], ['maxlength' => 20]) ?></div>
      <div class="col-12"><?= af_textarea('short_description', 'Short description', $v['short_description'], ['maxlength' => 500, 'rows' => 2]) ?></div>
      <div class="col-12"><?= af_textarea('description', 'Full description', $v['description'], ['rows' => 7], 'Basic HTML allowed: &lt;p&gt; &lt;ul&gt; &lt;li&gt; &lt;strong&gt; &lt;h3&gt; &lt;table&gt;') ?></div>
      <div class="col-md-6"><?= af_text('keywords', 'Search keywords', $v['keywords'], ['maxlength' => 500, 'placeholder' => 'atta, aata, gandum'], 'Extra words customers may search (Roman Urdu, spellings).') ?></div>
      <div class="col-md-6"><?= af_text('tags', 'Tags', $tags, ['placeholder' => 'class 5, maths']) ?></div>
    </div></div></div>

    <div class="card mb-3"><div class="card-header">Pricing &amp; stock</div><div class="card-body"><div class="row">
      <div class="col-md-3"><?= af_text('price', 'Regular price *', $v['price'], ['type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true]) ?></div>
      <div class="col-md-3"><?= af_text('sale_price', 'Sale price', $v['sale_price'], ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
      <div class="col-md-3"><?= af_text('cost_price', 'Purchase cost', $v['cost_price'], ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
      <div class="col-md-3 js-simple-stock"><?= af_text('stock', $p ? 'Current stock' : 'Opening stock', $p ? $stock : '0', ['type' => 'number', 'step' => 1], $p ? 'Changes are logged as adjustments.' : '') ?></div>
      <?php if ($p): ?><div class="col-md-6 js-simple-stock"><?= af_text('stock_reason', 'Reason for stock change (optional)', '', ['placeholder' => 'e.g. Stock count, damaged']) ?></div><?php endif; ?>
      <div class="col-md-3"><?= af_select('unit', 'Unit', units_list(), $v['unit']) ?></div>
      <div class="col-md-3"><?= af_text('unit_value', 'Unit size', $v['unit_value'], ['type' => 'number', 'step' => '0.001', 'placeholder' => 'e.g. 5 (kg)']) ?></div>
      <div class="col-md-3"><?= af_text('low_stock_threshold', 'Low-stock alert at', $v['low_stock_threshold'], ['type' => 'number', 'min' => 0]) ?></div>
      <div class="col-md-3"><?= af_text('min_order_qty', 'Min order qty', $v['min_order_qty'], ['type' => 'number', 'min' => 1]) ?></div>
      <div class="col-md-3"><?= af_text('max_order_qty', 'Max order qty', $v['max_order_qty'], ['type' => 'number', 'min' => 1, 'placeholder' => 'No limit']) ?></div>
      <div class="col-md-3"><?= af_text('weight_grams', 'Weight (grams)', $v['weight_grams'], ['type' => 'number', 'min' => 0]) ?></div>
      <div class="col-md-2"><?= af_text('length_cm', 'Length cm', $v['length_cm'], ['type' => 'number', 'step' => '0.1']) ?></div>
      <div class="col-md-2"><?= af_text('width_cm', 'Width cm', $v['width_cm'], ['type' => 'number', 'step' => '0.1']) ?></div>
      <div class="col-md-2"><?= af_text('height_cm', 'Height cm', $v['height_cm'], ['type' => 'number', 'step' => '0.1']) ?></div>
    </div></div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">Variations <small class="text-muted fw-normal">e.g. 5 kg / 10 kg / 20 kg, or colours. Each has its own price, SKU and stock.</small></div>
      <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle" id="varTable">
        <thead><tr><th>Option name</th><th>SKU</th><th>Barcode</th><th>Price</th><th>Sale</th><th>Cost</th><th>Stock</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($variations as $i => $va): ?>
        <tr><input type="hidden" name="var[<?= $i ?>][id]" value="<?= (int) $va['id'] ?>">
          <td><input class="form-control form-control-sm" name="var[<?= $i ?>][name]" value="<?= e($va['name']) ?>" required></td>
          <td><input class="form-control form-control-sm" name="var[<?= $i ?>][sku]" value="<?= e($va['sku']) ?>"></td>
          <td><input class="form-control form-control-sm" name="var[<?= $i ?>][barcode]" value="<?= e($va['barcode']) ?>"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm" name="var[<?= $i ?>][price]" value="<?= e($va['price']) ?>" style="width:90px"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm" name="var[<?= $i ?>][sale_price]" value="<?= e($va['sale_price']) ?>" style="width:90px"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm" name="var[<?= $i ?>][cost_price]" value="<?= e($va['cost_price']) ?>" style="width:90px"></td>
          <td><input type="number" class="form-control form-control-sm" name="var[<?= $i ?>][stock]" value="<?= (int) $va['stock'] ?>" style="width:80px"></td>
          <td><input type="checkbox" class="form-check-input" name="var[<?= $i ?>][is_active]" value="1" <?= $va['is_active'] ? 'checked' : '' ?>></td>
          <td><button type="button" class="btn btn-sm btn-light text-danger js-del-var"><i class="bi bi-x-lg"></i></button></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div><div class="p-2"><button type="button" class="btn btn-sm btn-outline-brand" id="addVar"><i class="bi bi-plus"></i> Add variation</button></div></div>
    </div>

    <div class="card mb-3"><div class="card-header">Images</div><div class="card-body">
      <?php if ($images): ?>
      <div class="row g-2 mb-3">
        <?php foreach ($images as $img): ?>
        <div class="col-6 col-md-3"><div class="border rounded p-2 h-100">
          <img src="<?= e(upload_url($img['thumb'] ?: $img['image'])) ?>" class="w-100 mb-2" style="height:120px;object-fit:contain" alt="">
          <div class="form-check small"><input class="form-check-input" type="radio" name="primary_image" value="<?= (int) $img['id'] ?>" id="pi<?= (int) $img['id'] ?>" <?= $img['is_primary'] ? 'checked' : '' ?>><label class="form-check-label" for="pi<?= (int) $img['id'] ?>">Main image</label></div>
          <input class="form-control form-control-sm mb-1" name="img[<?= (int) $img['id'] ?>][alt]" value="<?= e($img['alt_text']) ?>" placeholder="ALT text">
          <input type="number" class="form-control form-control-sm mb-1" name="img[<?= (int) $img['id'] ?>][sort]" value="<?= (int) $img['sort_order'] ?>" placeholder="Order">
          <div class="form-check small"><input class="form-check-input" type="checkbox" name="img[<?= (int) $img['id'] ?>][delete]" value="1" id="pd<?= (int) $img['id'] ?>"><label class="form-check-label text-danger" for="pd<?= (int) $img['id'] ?>">Delete</label></div>
        </div></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <label class="form-label" for="imgs">Upload images (you can select several)</label>
      <input type="file" id="imgs" name="images[]" class="form-control js-multi-img" data-preview="#imgPrev" multiple accept="image/jpeg,image/png,image/webp,image/gif">
      <div class="form-text">Images are resized and converted to WebP automatically. Preview:</div>
      <div id="imgPrev" class="mt-2"></div>
    </div></div>

    <div class="card mb-3"><div class="card-header">SEO</div><div class="card-body"><?= af_seo_fields($seo, 'product/', $v['slug']) ?></div></div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3 position-sticky" style="top:70px;z-index:2"><div class="card-body">
      <?= af_select('status', 'Status', ['active' => 'Active (visible)', 'inactive' => 'Inactive (hidden)', 'archived' => 'Archived'], $v['status']) ?>
      <?= af_check('track_stock', 'Track stock', $v['track_stock'], 'Untick for services or unlimited items.') ?>
      <?= af_check('is_featured', 'Featured product', $v['is_featured']) ?>
      <?= af_check('is_bestseller', 'Best seller', $v['is_bestseller']) ?>
      <?= af_check('is_rashan_item', 'Show in Monthly Rashan builder', $v['is_rashan_item']) ?>
      <hr>
      <button class="btn btn-brand w-100 mb-2"><i class="bi bi-save me-1"></i>Save product</button>
      <?php if ($p): ?><a href="<?= e(product_url($p)) ?>" target="_blank" class="btn btn-light w-100 mb-2">View on store</a>
      <a href="<?= aurl('movements.php?product=' . $id) ?>" class="btn btn-light w-100">Stock history</a><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">Categories</div><div class="card-body" style="max-height:340px;overflow:auto">
      <?php foreach (category_options() as $o): ?>
      <div class="form-check" style="margin-left:<?= $o['depth'] * 16 ?>px">
        <input class="form-check-input js-cat" type="checkbox" name="category_ids[]" value="<?= (int) $o['id'] ?>" id="cat<?= (int) $o['id'] ?>" <?= in_array((int) $o['id'], $catIds, true) ? 'checked' : '' ?>>
        <label class="form-check-label<?= $o['is_active'] ? '' : ' text-muted' ?>" for="cat<?= (int) $o['id'] ?>"><?= e($o['name']) ?></label>
      </div>
      <?php endforeach; ?>
    </div><div class="card-footer bg-white">
      <label class="form-label small" for="primCat">Primary category (breadcrumbs)</label>
      <select name="primary_category_id" id="primCat" class="form-select form-select-sm"><option value="">First selected</option>
        <?php foreach (category_options() as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $v['primary_category_id'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select>
    </div></div>
    <div class="card mb-3"><div class="card-header">Related products</div><div class="card-body">
      <input type="search" class="form-control form-control-sm js-product-picker" data-results="#relResults" placeholder="Search products to add…">
      <div class="list-group mt-1 small" id="relResults"></div>
      <ul class="list-group mt-2" id="relList">
        <?php foreach ($related as $r): ?><li class="list-group-item d-flex justify-content-between small"><?= e($r['name']) ?><input type="hidden" name="related[]" value="<?= (int) $r['id'] ?>"><button type="button" class="btn btn-sm btn-link text-danger p-0 js-rm">remove</button></li><?php endforeach; ?>
      </ul>
    </div></div>
  </div>
</div>
</form>
<?php clear_old(); admin_footer(); ?>
<script>
var vi = <?= count($variations) ?>;
function syncVar() { var has = $('#varTable tbody tr').length > 0; $('.js-simple-stock').toggle(!has); }
$('#addVar').on('click', function () {
  var i = vi++;
  $('#varTable tbody').append('<tr><input type="hidden" name="var[' + i + '][id]" value="0">'
    + '<td><input class="form-control form-control-sm" name="var[' + i + '][name]" placeholder="e.g. 5 kg" required></td>'
    + '<td><input class="form-control form-control-sm" name="var[' + i + '][sku]" placeholder="Auto"></td>'
    + '<td><input class="form-control form-control-sm" name="var[' + i + '][barcode]"></td>'
    + '<td><input type="number" step="0.01" class="form-control form-control-sm" name="var[' + i + '][price]" style="width:90px" required></td>'
    + '<td><input type="number" step="0.01" class="form-control form-control-sm" name="var[' + i + '][sale_price]" style="width:90px"></td>'
    + '<td><input type="number" step="0.01" class="form-control form-control-sm" name="var[' + i + '][cost_price]" style="width:90px"></td>'
    + '<td><input type="number" class="form-control form-control-sm" name="var[' + i + '][stock]" value="0" style="width:80px"></td>'
    + '<td><input type="checkbox" class="form-check-input" name="var[' + i + '][is_active]" value="1" checked></td>'
    + '<td><button type="button" class="btn btn-sm btn-light text-danger js-del-var"><i class="bi bi-x-lg"></i></button></td></tr>');
  syncVar();
});
$(document).on('click', '.js-del-var', function () { if (confirm('Remove this variation?')) { $(this).closest('tr').remove(); syncVar(); } });
$(document).on('click', '#relResults .js-pick', function () {
  var id = $(this).data('id');
  if (!$('#relList input[value="' + id + '"]').length) $('#relList').append('<li class="list-group-item d-flex justify-content-between small">' + FSA.esc($(this).data('name')) + '<input type="hidden" name="related[]" value="' + id + '"><button type="button" class="btn btn-sm btn-link text-danger p-0 js-rm">remove</button></li>');
  $('#relResults').empty(); $('.js-product-picker').val('');
});
$(document).on('click', '.js-rm', function () { $(this).closest('li').remove(); });
syncVar();
</script>
