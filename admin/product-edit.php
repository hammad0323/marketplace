<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('products.view');

$id = input_int('id', 0, 'get');
$product = $id ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    flash('error', 'Product not found.');
    redirect(admin_url('products'));
}
$canCost = can('products.cost_price');
$errors = [];

if (is_post()) {
    require_csrf();
    require_admin('products.edit');
    $d = [
        'name' => input('name'),
        'slug' => slugify(input('slug') ?: input('name')),
        'sku' => strtoupper(preg_replace('/[^A-Za-z0-9\-_.]/', '', input('sku'))),
        'category_id' => input_int('category_id'),
        'subcategory_id' => input_int('subcategory_id') ?: null,
        'status' => in_array(input('status'), ['draft', 'published', 'inactive'], true) ? input('status') : 'draft',
        'short_description' => mb_substr(input('short_description'), 0, 500) ?: null,
        'description' => sanitize_html($_POST['description'] ?? '') ?: null,
        'regular_price' => input_float('regular_price'),
        'sale_price' => input_float('sale_price'),
        'track_stock' => input_bool('track_stock'),
        'stock_status' => in_array(input('stock_status'), ['in_stock', 'out_of_stock', 'preorder'], true) ? input('stock_status') : 'in_stock',
        'low_stock_threshold' => max(0, input_int('low_stock_threshold', 5)),
        'is_featured' => input_bool('is_featured'),
        'is_new_arrival' => input_bool('is_new_arrival'),
        'is_best_seller' => input_bool('is_best_seller'),
        'wallet_type' => mb_substr(input('wallet_type'), 0, 60) ?: null,
        'material' => mb_substr(input('material'), 0, 120) ?: null,
        'finish' => mb_substr(input('finish'), 0, 120) ?: null,
        'care_instructions' => sanitize_html($_POST['care_instructions'] ?? '') ?: null,
        'weight_grams' => input_int('weight_grams') ?: null,
        'length_mm' => input_float('length_mm'),
        'width_mm' => input_float('width_mm'),
        'height_mm' => input_float('height_mm'),
        'gift_wrap_available' => input_bool('gift_wrap_available'),
        'gift_wrap_price' => max(0, (float) input_float('gift_wrap_price', 0)),
        'seo_title' => mb_substr(input('seo_title'), 0, 190) ?: null,
        'meta_description' => mb_substr(input('meta_description'), 0, 320) ?: null,
        'canonical_url' => input('canonical_url') ?: null,
        'og_title' => mb_substr(input('og_title'), 0, 190) ?: null,
        'og_description' => mb_substr(input('og_description'), 0, 320) ?: null,
    ];
    if ($canCost) {
        $d['cost_price'] = input_float('cost_price');
    }
    // Validation
    if ($d['name'] === '' || mb_strlen($d['name']) > 190) {
        $errors[] = 'Product name is required (max 190 characters).';
    }
    if ($d['sku'] === '') {
        $errors[] = 'SKU is required.';
    } elseif (db_val('SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $id]) || db_val('SELECT COUNT(*) FROM product_variants WHERE sku = ? AND product_id <> ?', [$d['sku'], $id])) {
        $errors[] = 'SKU "' . $d['sku'] . '" is already used by another product.';
    }
    if (db_val('SELECT COUNT(*) FROM products WHERE slug = ? AND id <> ?', [$d['slug'], $id])) {
        $errors[] = 'The URL slug "' . $d['slug'] . '" is already in use.';
    }
    $cat = db_one('SELECT * FROM categories WHERE id = ?', [$d['category_id']]);
    if (!$cat) {
        $errors[] = 'Please choose a category.';
    }
    if ($d['subcategory_id']) {
        $sub = db_one('SELECT * FROM categories WHERE id = ?', [$d['subcategory_id']]);
        if (!$sub || !in_array((int) $sub['id'], category_descendant_ids_all((int) $d['category_id']), true) || (int) $sub['id'] === (int) $d['category_id']) {
            $errors[] = 'The subcategory must belong to the selected category.';
        }
    }
    if ($d['regular_price'] === null || $d['regular_price'] < 0) {
        $errors[] = 'Regular price must be zero or more.';
    }
    if ($d['sale_price'] !== null && ($d['sale_price'] <= 0 || $d['sale_price'] >= (float) $d['regular_price'])) {
        $errors[] = 'Sale price must be lower than the regular price (leave blank for no sale).';
    }
    if ($d['canonical_url'] && !preg_match('#^https?://#', $d['canonical_url'])) {
        $errors[] = 'Canonical URL must be a full https:// address.';
    }
    // Variants payload
    $variantsIn = [];
    foreach (input_array('variants') as $key => $v) {
        if (!is_array($v) || trim($v['label'] ?? '') === '') {
            continue;
        }
        $vsku = strtoupper(preg_replace('/[^A-Za-z0-9\-_.]/', '', (string) ($v['sku'] ?? '')));
        $variantsIn[] = [
            'id' => (int) ($v['id'] ?? 0),
            'label' => mb_substr(trim($v['label']), 0, 120),
            'sku' => $vsku ?: ($d['sku'] . '-' . strtoupper(substr(slugify($v['label']), 0, 6))),
            'color_name' => mb_substr(trim($v['color_name'] ?? ''), 0, 60) ?: null,
            'color_hex' => valid_hex(trim($v['color_hex'] ?? '')) ? strtoupper(trim($v['color_hex'])) : null,
            'price_override' => is_numeric($v['price_override'] ?? '') && (float) $v['price_override'] > 0 ? round((float) $v['price_override'], 2) : null,
            'sale_override' => is_numeric($v['sale_override'] ?? '') && (float) $v['sale_override'] > 0 ? round((float) $v['sale_override'], 2) : null,
            'image_id' => (int) ($v['image_id'] ?? 0) ?: null,
            'stock' => (int) ($v['stock'] ?? 0),
            'is_active' => !empty($v['is_active']) ? 1 : 0,
        ];
    }
    $skus = array_column($variantsIn, 'sku');
    if (count($skus) !== count(array_unique($skus))) {
        $errors[] = 'Each variant needs a unique SKU.';
    }
    foreach ($variantsIn as $v) {
        if (db_val('SELECT COUNT(*) FROM product_variants WHERE sku = ? AND id <> ?', [$v['sku'], $v['id']]) || db_val('SELECT COUNT(*) FROM products WHERE sku = ?', [$v['sku']])) {
            $errors[] = 'Variant SKU "' . $v['sku'] . '" is already in use.';
        }
    }
    // OG image upload
    [$ogImage, $ogErr] = handle_image_field('og_image', $product['og_image'] ?? null, 'seo');
    if ($ogErr) {
        $errors[] = 'Open Graph image: ' . $ogErr;
    }
    $d['og_image'] = $ogImage ?: null;

    if (!$errors) {
        try {
            $savedId = db_tx(function () use ($d, $id, $product, $variantsIn, $canCost) {
                if ($d['status'] === 'published' && (!$product || !$product['published_at'])) {
                    $d['published_at'] = now();
                }
                if ($product) {
                    db_update('products', $d, 'id = ?', [$id]);
                    if ($product['slug'] !== $d['slug'] && $product['status'] === 'published') {
                        add_slug_redirect('product', $product['slug'], $d['slug']);
                    }
                    $pid = $id;
                } else {
                    $pid = db_insert('products', $d);
                }
                // --- Images: update existing, delete marked, upload new
                $existing = db_all('SELECT * FROM product_images WHERE product_id = ?', [$pid]);
                $imgIn = input_array('images');
                $mainId = input_int('main_image');
                foreach ($existing as $img) {
                    $row = $imgIn[$img['id']] ?? null;
                    if ($row && !empty($row['delete'])) {
                        db_exec('UPDATE product_variants SET image_id = NULL WHERE image_id = ?', [$img['id']]);
                        db_exec('DELETE FROM product_images WHERE id = ?', [$img['id']]);
                        if (!db_val('SELECT COUNT(*) FROM product_images WHERE file_path = ?', [$img['file_path']])) {
                            delete_upload($img['file_path']);
                        }
                        continue;
                    }
                    if ($row) {
                        db_update('product_images', [
                            'alt_text' => mb_substr(trim($row['alt_text'] ?? ''), 0, 190) ?: null,
                            'caption' => mb_substr(trim($row['caption'] ?? ''), 0, 255) ?: null,
                            'sort_order' => (int) ($row['sort_order'] ?? 0),
                            'is_main' => (int) $img['id'] === $mainId ? 1 : 0,
                        ], 'id = ?', [$img['id']]);
                    }
                }
                $uploadErrors = [];
                $sort = (int) db_val('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?', [$pid]);
                foreach (uploaded_files('new_images') as $file) {
                    [$path, $err] = store_image_upload($file, 'products');
                    if ($err) {
                        $uploadErrors[] = $file['name'] . ': ' . $err;
                        continue;
                    }
                    db_insert('product_images', ['product_id' => $pid, 'file_path' => $path, 'alt_text' => $d['name'], 'sort_order' => ++$sort, 'is_main' => 0]);
                }
                if (!db_val('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1', [$pid])) {
                    db_exec('UPDATE product_images SET is_main = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$pid]);
                }
                $validImageIds = array_map('intval', db_col('SELECT id FROM product_images WHERE product_id = ?', [$pid]));

                // --- Variants & inventory
                $keepIds = [];
                foreach ($variantsIn as $i => $v) {
                    $row = [
                        'label' => $v['label'], 'sku' => $v['sku'], 'color_name' => $v['color_name'], 'color_hex' => $v['color_hex'],
                        'price_override' => $v['price_override'], 'sale_override' => $v['sale_override'],
                        'image_id' => in_array((int) $v['image_id'], $validImageIds, true) ? $v['image_id'] : null,
                        'sort_order' => $i, 'is_active' => $v['is_active'],
                    ];
                    $vid = $v['id'] && db_val('SELECT COUNT(*) FROM product_variants WHERE id = ? AND product_id = ?', [$v['id'], $pid]) ? $v['id'] : 0;
                    if ($vid) {
                        db_update('product_variants', $row, 'id = ?', [$vid]);
                    } else {
                        $vid = db_insert('product_variants', $row + ['product_id' => $pid]);
                    }
                    $keepIds[] = $vid;
                    if ($v['color_name']) {
                        db_exec('INSERT INTO product_variant_values (variant_id, attribute_name, attribute_value) VALUES (?, \'Colour\', ?) ON DUPLICATE KEY UPDATE attribute_value = VALUES(attribute_value)', [$vid, $v['color_name']]);
                    }
                    $current = db_val('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_id = ?', [$pid, $vid]);
                    if ($current === null) {
                        db_insert('product_inventory', ['product_id' => $pid, 'variant_id' => $vid, 'quantity' => 0]);
                        $current = 0;
                    }
                    if ((int) $current !== $v['stock']) {
                        inventory_adjust($pid, $vid, $v['stock'] - (int) $current, 'manual', null, 'Product editor');
                    }
                }
                $remove = db_col('SELECT id FROM product_variants WHERE product_id = ?' . ($keepIds ? ' AND id NOT IN (' . db_in($keepIds) . ')' : ''), array_merge([$pid], $keepIds));
                if ($remove) {
                    db_exec('DELETE FROM product_variants WHERE id IN (' . db_in($remove) . ')', $remove);
                }
                if ($keepIds) {
                    db_exec('DELETE FROM product_inventory WHERE product_id = ? AND variant_id IS NULL', [$pid]);
                } else {
                    $stock = input_int('stock_quantity');
                    $current = db_val('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_id IS NULL', [$pid]);
                    if ($current === null) {
                        db_insert('product_inventory', ['product_id' => $pid, 'variant_id' => null, 'quantity' => 0]);
                        $current = 0;
                    }
                    if ((int) $current !== $stock) {
                        inventory_adjust($pid, null, $stock - (int) $current, 'manual', null, 'Product editor');
                    }
                }

                // --- Relations & collections
                db_exec('DELETE FROM product_relations WHERE product_id = ?', [$pid]);
                foreach (['related', 'cross_sell', 'upsell'] as $type) {
                    foreach (array_values(array_unique(array_map('intval', input_array($type)))) as $k => $rid) {
                        if ($rid && $rid !== $pid) {
                            db_exec('INSERT IGNORE INTO product_relations (product_id, related_id, relation_type, sort_order) SELECT ?, id, ?, ? FROM products WHERE id = ?', [$pid, $type, $k, $rid]);
                        }
                    }
                }
                if (can('collections.manage')) {
                    db_exec('DELETE FROM collection_products WHERE product_id = ?', [$pid]);
                    foreach (array_unique(array_map('intval', input_array('collections'))) as $cid) {
                        db_exec('INSERT IGNORE INTO collection_products (collection_id, product_id) SELECT id, ? FROM collections WHERE id = ?', [$pid, $cid]);
                    }
                }
                if ($uploadErrors) {
                    $GLOBALS['upload_errors'] = $uploadErrors;
                }
                return $pid;
            });
        } catch (Throwable $e) {
            error_log('Product save failed: ' . $e->getMessage());
            $errors[] = 'Could not save the product: ' . $e->getMessage();
        }
        if (!$errors) {
            audit_log($product ? 'product_updated' : 'product_created', 'product', $savedId, ['name' => $d['name'], 'status' => $d['status']]);
            foreach ($GLOBALS['upload_errors'] ?? [] as $ue) {
                flash('warning', $ue);
            }
            flash('success', 'Product saved.');
            redirect(admin_url('product-edit', ['id' => $savedId]) . '#' . input('active_tab', 'general'));
        }
    }
    // Re-populate form after errors
    $product = array_merge($product ?? [], $d, ['id' => $id]);
}

/** All descendants regardless of active state (for validation). */
function category_descendant_ids_all(int $id): array
{
    $ids = [$id];
    $frontier = [$id];
    while ($frontier) {
        $children = array_map('intval', db_col('SELECT id FROM categories WHERE parent_id IN (' . db_in($frontier) . ')', $frontier));
        $ids = array_merge($ids, $children);
        $frontier = $children;
    }
    return $ids;
}

$p = $product ?? ['status' => 'draft', 'track_stock' => 1, 'low_stock_threshold' => 5, 'stock_status' => 'in_stock', 'gift_wrap_available' => 0, 'gift_wrap_price' => 0];
$images = $id ? db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$id]) : [];
$variants = $id ? product_variants($id, false) : [];
$baseStock = $id ? (int) db_val('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_id IS NULL', [$id]) : 0;
$relations = ['related' => [], 'cross_sell' => [], 'upsell' => []];
if ($id) {
    foreach (db_all('SELECT related_id, relation_type FROM product_relations WHERE product_id = ? ORDER BY sort_order', [$id]) as $r) {
        $relations[$r['relation_type']][] = $r['related_id'];
    }
}
$inCollections = $id ? db_col('SELECT collection_id FROM collection_products WHERE product_id = ?', [$id]) : [];
$collections = db_all('SELECT id, name FROM collections ORDER BY sort_order, name');
$productOpts = product_options();
unset($productOpts[$id]);
$topCats = ['' => 'Select…'];
foreach (db_all('SELECT id, name, is_active FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name') as $c) {
    $topCats[$c['id']] = $c['name'] . ((int) $c['is_active'] ? '' : ' (disabled)');
}
$allSubs = db_all('SELECT id, parent_id, name FROM categories WHERE parent_id IS NOT NULL ORDER BY sort_order, name');
$imgOptions = ['' => '— Default —'];
foreach ($images as $i => $img) {
    $imgOptions[$img['id']] = 'Image ' . ($i + 1) . ($img['alt_text'] ? ' — ' . mb_substr($img['alt_text'], 0, 30) : '');
}
$readonly = !can('products.edit');

admin_header($id ? 'Edit: ' . ($p['name'] ?? '') : 'Add product', 'products');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" id="productForm">
<?= csrf_field() ?>
<input type="hidden" name="active_tab" id="activeTab" value="general">
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <a href="<?= e(admin_url('products')) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Products</a>
  <?php if ($id): ?>
    <span class="ms-2"><?= badge($p['status']) ?></span>
    <a class="btn btn-sm btn-light ms-auto" target="_blank" href="<?= e(path_url('product/' . $p['slug'])) ?>"><i class="bi bi-eye"></i> View<?= $p['status'] !== 'published' ? ' (preview)' : '' ?></a>
  <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <?php foreach (['general' => 'General', 'pricing' => 'Pricing & stock', 'variants' => 'Variants', 'images' => 'Images', 'details' => 'Details', 'merch' => 'Merchandising', 'seo' => 'SEO'] as $k => $label): ?>
    <li class="nav-item"><button class="nav-link<?= $k === 'general' ? ' active' : '' ?>" type="button" data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>" data-tab="<?= $k ?>"><?= e($label) ?></button></li>
  <?php endforeach; ?>
</ul>
<fieldset <?= $readonly ? 'disabled' : '' ?>>
<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-general"><div class="card"><div class="card-body"><div class="row">
    <div class="col-lg-8">
      <?= f_text('name', 'Product name', $p['name'] ?? '', ['required' => true, 'maxlength' => 190, 'data-slug-source' => '#f_slug']) ?>
      <div class="row">
        <div class="col-md-6"><?= f_text('sku', 'SKU (unique)', $p['sku'] ?? '', ['required' => true, 'maxlength' => 64]) ?></div>
        <div class="col-md-6"><?= f_text('slug', 'URL slug', $p['slug'] ?? '', ['maxlength' => 190, 'data-slug-target' => true], '/product/your-slug — changing it creates a 301 redirect automatically.') ?></div>
      </div>
      <?= f_textarea('short_description', 'Short description', $p['short_description'] ?? '', ['rows' => 3, 'maxlength' => 500]) ?>
      <?= f_textarea('description', 'Full description', $p['description'] ?? '', ['rows' => 10], 'Basic HTML allowed: <p>, <strong>, <em>, <ul>, <li>, <h2>, <h3>, <a>. Scripts and styles are removed.') ?>
    </div>
    <div class="col-lg-4">
      <?= f_select('status', 'Status', ['draft' => 'Draft', 'published' => 'Published', 'inactive' => 'Inactive'], $p['status'] ?? 'draft') ?>
      <?= f_select('category_id', 'Category', $topCats, $p['category_id'] ?? '', ['required' => true]) ?>
      <div class="mb-3"><label class="form-label" for="subSelect">Subcategory</label>
        <select class="form-select" name="subcategory_id" id="subSelect"><option value="">— None —</option>
          <?php foreach ($allSubs as $s): ?><option value="<?= (int) $s['id'] ?>" data-parent="<?= (int) $s['parent_id'] ?>"<?= (int) ($p['subcategory_id'] ?? 0) === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></div>
      <?php if ($id && !empty($p['created_at'])): ?><p class="small text-muted">Created <?= e(format_date($p['created_at'], true)) ?><br>Updated <?= e(format_date($p['updated_at'], true)) ?></p><?php endif; ?>
    </div>
  </div></div></div></div>

  <div class="tab-pane fade" id="tab-pricing"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= f_number('regular_price', 'Regular price (' . currency_code() . ')', $p['regular_price'] ?? '', ['required' => true, 'min' => 0]) ?></div>
    <div class="col-md-4"><?= f_number('sale_price', 'Sale price', $p['sale_price'] ?? '', ['min' => 0], 'Leave blank for no sale.') ?></div>
    <div class="col-md-4"><?php if ($canCost): ?><?= f_number('cost_price', 'Cost price (private)', $p['cost_price'] ?? '', ['min' => 0], 'Visible only to roles with the cost-price permission.') ?><?php else: ?><p class="text-muted small mt-4"><i class="bi bi-lock"></i> Cost price restricted.</p><?php endif; ?></div>
    <div class="col-12"><hr></div>
    <div class="col-md-4"><?= f_check('track_stock', 'Track stock quantity', (int) ($p['track_stock'] ?? 1)) ?></div>
    <div class="col-md-4"><?= f_number('low_stock_threshold', 'Low-stock threshold', $p['low_stock_threshold'] ?? 5, ['min' => 0]) ?></div>
    <div class="col-md-4"><?= f_select('stock_status', 'Stock status (when not tracking)', ['in_stock' => 'In stock', 'out_of_stock' => 'Out of stock', 'preorder' => 'Pre-order'], $p['stock_status'] ?? 'in_stock') ?></div>
    <div class="col-md-4"><?= f_number('stock_quantity', 'Stock quantity (products without variants)', $baseStock, ['min' => 0, 'step' => 1], $variants ? 'This product has variants — stock is managed per variant.' : null) ?></div>
    <div class="col-12"><hr></div>
    <div class="col-md-4"><?= f_check('gift_wrap_available', 'Offer gift packaging', (int) ($p['gift_wrap_available'] ?? 0)) ?></div>
    <div class="col-md-4"><?= f_number('gift_wrap_price', 'Gift packaging price (per item)', $p['gift_wrap_price'] ?? 0, ['min' => 0], '0 = complimentary.') ?></div>
  </div></div></div></div>

  <div class="tab-pane fade" id="tab-variants"><div class="card"><div class="card-body">
    <p class="text-muted small">Add a row per colour or option. Each variant has its own SKU and stock. Price fields override the product price for that variant only. To link an image, upload images first and save.</p>
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><th>Label</th><th>SKU</th><th>Colour name</th><th>Swatch</th><th>Price override</th><th>Sale override</th><th>Image</th><th>Stock</th><th>Active</th><th></th></tr></thead>
      <tbody id="variantRows">
        <?php foreach ($variants as $i => $v): ?>
          <tr>
            <td><input type="hidden" name="variants[<?= $i ?>][id]" value="<?= (int) $v['id'] ?>"><input class="form-control form-control-sm" name="variants[<?= $i ?>][label]" value="<?= e($v['label']) ?>" required></td>
            <td><input class="form-control form-control-sm" name="variants[<?= $i ?>][sku]" value="<?= e($v['sku']) ?>" style="min-width:130px"></td>
            <td><input class="form-control form-control-sm" name="variants[<?= $i ?>][color_name]" value="<?= e($v['color_name']) ?>"></td>
            <td><input class="form-control form-control-sm form-control-color" type="color" name="variants[<?= $i ?>][color_hex]" value="<?= e($v['color_hex'] ?: '#999999') ?>"></td>
            <td><input class="form-control form-control-sm" type="number" step="any" min="0" name="variants[<?= $i ?>][price_override]" value="<?= e($v['price_override']) ?>"></td>
            <td><input class="form-control form-control-sm" type="number" step="any" min="0" name="variants[<?= $i ?>][sale_override]" value="<?= e($v['sale_override']) ?>"></td>
            <td><select class="form-select form-select-sm" name="variants[<?= $i ?>][image_id]"><?php foreach ($imgOptions as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $v['image_id'] === (string) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
            <td><input class="form-control form-control-sm" type="number" step="1" name="variants[<?= $i ?>][stock]" value="<?= (int) $v['stock_qty'] ?>" style="width:90px"></td>
            <td><input class="form-check-input" type="checkbox" name="variants[<?= $i ?>][is_active]" value="1" <?= $v['is_active'] ? 'checked' : '' ?>></td>
            <td><button type="button" class="btn btn-sm btn-light" data-variant-remove title="Remove"><i class="bi bi-trash"></i></button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <template id="variantTpl"><tr>
      <td><input type="hidden" name="variants[__i__][id]" value="0"><input class="form-control form-control-sm" name="variants[__i__][label]" placeholder="e.g. Cognac" required></td>
      <td><input class="form-control form-control-sm" name="variants[__i__][sku]" placeholder="auto"></td>
      <td><input class="form-control form-control-sm" name="variants[__i__][color_name]"></td>
      <td><input class="form-control form-control-sm form-control-color" type="color" name="variants[__i__][color_hex]" value="#8B4A22"></td>
      <td><input class="form-control form-control-sm" type="number" step="any" min="0" name="variants[__i__][price_override]"></td>
      <td><input class="form-control form-control-sm" type="number" step="any" min="0" name="variants[__i__][sale_override]"></td>
      <td><select class="form-select form-select-sm" name="variants[__i__][image_id]"><?php foreach ($imgOptions as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control form-control-sm" type="number" step="1" name="variants[__i__][stock]" value="0" style="width:90px"></td>
      <td><input class="form-check-input" type="checkbox" name="variants[__i__][is_active]" value="1" checked></td>
      <td><button type="button" class="btn btn-sm btn-light" data-variant-remove><i class="bi bi-trash"></i></button></td>
    </tr></template>
    <button type="button" class="btn btn-sm btn-outline-primary" data-variant-add><i class="bi bi-plus"></i> Add variant</button>
  </div></div></div>

  <div class="tab-pane fade" id="tab-images"><div class="card"><div class="card-body">
    <div class="gallery-admin mb-4">
      <?php foreach ($images as $img): ?>
        <div class="gallery-admin__item<?= $img['is_main'] ? ' is-main' : '' ?>">
          <img src="<?= e(media_url($img['file_path'])) ?>" alt="">
          <label class="form-check small mt-2"><input class="form-check-input" type="radio" name="main_image" value="<?= (int) $img['id'] ?>" <?= $img['is_main'] ? 'checked' : '' ?>> Main image</label>
          <input class="form-control form-control-sm mt-1" name="images[<?= (int) $img['id'] ?>][alt_text]" value="<?= e($img['alt_text']) ?>" placeholder="Alt text" maxlength="190">
          <input class="form-control form-control-sm mt-1" name="images[<?= (int) $img['id'] ?>][caption]" value="<?= e($img['caption']) ?>" placeholder="Caption (optional)" maxlength="255">
          <div class="d-flex gap-2 mt-1 align-items-center"><input class="form-control form-control-sm" type="number" name="images[<?= (int) $img['id'] ?>][sort_order]" value="<?= (int) $img['sort_order'] ?>" title="Sort order" style="width:70px">
            <label class="form-check small text-danger mb-0"><input class="form-check-input" type="checkbox" name="images[<?= (int) $img['id'] ?>][delete]" value="1"> Delete</label></div>
        </div>
      <?php endforeach; ?>
    </div>
    <label class="form-label" for="newImages">Upload images</label>
    <input class="form-control" type="file" name="new_images[]" id="newImages" multiple accept="image/jpeg,image/png,image/webp">
    <div class="form-text">JPG, PNG or WebP up to 5 MB each. Images are re-encoded, resized to max 2400px and renamed. The first image is used as the main image unless you choose another; the second appears on hover in product cards. Recommended ratio 4:5.</div>
  </div></div></div>

  <div class="tab-pane fade" id="tab-details"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= f_text('wallet_type', 'Type (filter)', $p['wallet_type'] ?? '', ['list' => 'typeList', 'maxlength' => 60]) ?>
      <datalist id="typeList"><?php foreach (db_col('SELECT DISTINCT wallet_type FROM products WHERE wallet_type IS NOT NULL') as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div>
    <div class="col-md-4"><?= f_text('material', 'Material (filter)', $p['material'] ?? '', ['list' => 'matList', 'maxlength' => 120], 'Only describe materials you can verify.') ?>
      <datalist id="matList"><?php foreach (db_col('SELECT DISTINCT material FROM products WHERE material IS NOT NULL') as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div>
    <div class="col-md-4"><?= f_text('finish', 'Finish', $p['finish'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-md-3"><?= f_number('weight_grams', 'Weight (g)', $p['weight_grams'] ?? '', ['min' => 0, 'step' => 1]) ?></div>
    <div class="col-md-3"><?= f_number('length_mm', 'Length (mm)', $p['length_mm'] ?? '', ['min' => 0]) ?></div>
    <div class="col-md-3"><?= f_number('width_mm', 'Width (mm)', $p['width_mm'] ?? '', ['min' => 0]) ?></div>
    <div class="col-md-3"><?= f_number('height_mm', 'Depth (mm)', $p['height_mm'] ?? '', ['min' => 0]) ?></div>
    <div class="col-12"><?= f_textarea('care_instructions', 'Care instructions', $p['care_instructions'] ?? '', ['rows' => 4]) ?></div>
  </div></div></div></div>

  <div class="tab-pane fade" id="tab-merch"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= f_check('is_featured', 'Featured product', (int) ($p['is_featured'] ?? 0)) ?></div>
    <div class="col-md-4"><?= f_check('is_new_arrival', 'New arrival', (int) ($p['is_new_arrival'] ?? 0)) ?></div>
    <div class="col-md-4"><?= f_check('is_best_seller', 'Best seller (manual flag)', (int) ($p['is_best_seller'] ?? 0), 'Used when automatic sales data is insufficient.') ?></div>
    <div class="col-md-4"><?= f_select('related', 'Related products', $productOpts, $relations['related'], ['multiple' => true, 'size' => 8]) ?></div>
    <div class="col-md-4"><?= f_select('cross_sell', 'Cross-sells ("Complete the set")', $productOpts, $relations['cross_sell'], ['multiple' => true, 'size' => 8]) ?></div>
    <div class="col-md-4"><?= f_select('upsell', 'Upsells ("Consider also")', $productOpts, $relations['upsell'], ['multiple' => true, 'size' => 8]) ?></div>
    <?php if (can('collections.manage')): ?><div class="col-md-6"><?= f_select('collections', 'Collections', array_column($collections, 'name', 'id'), $inCollections, ['multiple' => true, 'size' => 4]) ?></div><?php endif; ?>
    <p class="small text-muted">Hold Ctrl / Cmd to select multiple.</p>
  </div></div></div></div>

  <div class="tab-pane fade" id="tab-seo"><div class="card"><div class="card-body"><div class="row">
    <div class="col-lg-7">
      <?= f_text('seo_title', 'SEO title', $p['seo_title'] ?? '', ['maxlength' => 190, 'data-count' => 60], 'Blank = product name + material.') ?>
      <?= f_textarea('meta_description', 'Meta description', $p['meta_description'] ?? '', ['rows' => 3, 'maxlength' => 320, 'data-count' => 160], 'Blank = generated from the short description.') ?>
      <?= f_text('canonical_url', 'Canonical URL (optional)', $p['canonical_url'] ?? '', ['maxlength' => 255], 'Only set when another URL should be indexed instead. Blank = this product URL.') ?>
      <?= f_text('og_title', 'Open Graph / social title', $p['og_title'] ?? '', ['maxlength' => 190]) ?>
      <?= f_textarea('og_description', 'Open Graph / social description', $p['og_description'] ?? '', ['rows' => 2, 'maxlength' => 320]) ?>
      <?= f_image('og_image', 'Social share image', $p['og_image'] ?? null, 'Blank = main product image. 1200×630 recommended.') ?>
    </div>
    <div class="col-lg-5">
      <div class="border rounded p-3 bg-white">
        <small class="text-muted">Search preview</small>
        <div style="color:#1a0dab;font-size:1.05rem"><?= e(($p['seo_title'] ?? '') ?: (($p['name'] ?? 'Product') . (!empty($p['material']) ? ' — ' . $p['material'] : ''))) ?> | <?= e(setting('site_name')) ?></div>
        <div style="color:#006621;font-size:.8rem"><?= e(url('product/' . ($p['slug'] ?? ''))) ?></div>
        <div style="color:#545454;font-size:.85rem"><?= e(excerpt(($p['meta_description'] ?? '') ?: ($p['short_description'] ?? ''), 158)) ?></div>
      </div>
      <p class="small text-muted mt-2">Product and breadcrumb structured data (JSON-LD) are generated automatically.</p>
    </div>
  </div></div></div></div>
</div>
<div class="sticky-actions d-flex gap-2"><?= f_submit($id ? 'Save product' : 'Create product') ?>
  <?php if ($id): ?><button type="submit" form="dupForm" class="btn btn-light">Duplicate</button><?php endif; ?>
  <?php if ($id && can('products.delete')): ?><button type="submit" form="delForm" class="btn btn-outline-danger ms-auto">Delete</button><?php endif; ?>
</div>
</fieldset>
</form>
<?php if ($id): ?>
<form id="dupForm" method="post" action="<?= e(admin_url('product-action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
<form id="delForm" method="post" action="<?= e(admin_url('product-action')) ?>" data-confirm="Delete this product?" data-confirm-text="Products with orders are archived instead of deleted."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var cat = document.getElementById('f_category_id'), sub = document.getElementById('subSelect');
  function filterSubs() { [].forEach.call(sub.options, function (o) { if (!o.value) return; var show = o.dataset.parent === cat.value; o.hidden = !show; if (!show && o.selected) sub.value = ''; }); }
  cat.addEventListener('change', filterSubs); filterSubs();
  document.querySelectorAll('[data-tab]').forEach(function (b) { b.addEventListener('shown.bs.tab', function () { document.getElementById('activeTab').value = b.dataset.tab; history.replaceState(null, '', '#' + b.dataset.tab); }); });
  var h = location.hash.replace('#', ''); var t = document.querySelector('[data-tab="' + h + '"]'); if (t) bootstrap.Tab.getOrCreateInstance(t).show();
});
</script>
<?php admin_footer();
