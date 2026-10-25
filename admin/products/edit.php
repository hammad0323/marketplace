<?php
/**
 * Create / edit a product: details, pricing, stock, images, variants, attributes,
 * customisation, merchandising, relations and SEO.
 */
require __DIR__ . '/../includes/init.php';
admin_require('products');
$canCost = admin_can('products.cost');
$id = input_int('id');
$product = $id ? db_row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    flash('error', 'Product not found.');
    redirect('admin/products/');
}
$errors = [];

if (is_post()) {
    $d = [
        'name' => mb_substr(input('name', '', 'post'), 0, 190),
        'sku' => strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', input('sku', '', 'post'))),
        'category_id' => input_int('category_id') ?: null,
        'short_description' => nullable(mb_substr(input('short_description', '', 'post'), 0, 600)),
        'description' => sanitize_html($_POST['description'] ?? ''),
        'price' => (float) input_float('price', 0),
        'sale_price' => input_float('sale_price'),
        'track_stock' => input_bool('track_stock'),
        'low_stock_threshold' => max(0, input_int('low_stock_threshold', 5)),
        'allow_backorder' => input_bool('allow_backorder'),
        'weight_kg' => input_float('weight_kg'),
        'dimensions' => nullable(mb_substr(input('dimensions', '', 'post'), 0, 80)),
        'care_instructions' => nullable(mb_substr(input('care_instructions', '', 'post'), 0, 3000)),
        'size_guide' => nullable(sanitize_html($_POST['size_guide'] ?? '')),
        'video_url' => nullable(filter_var(input('video_url', '', 'post'), FILTER_VALIDATE_URL) ?: ''),
        'ready_to_ship' => input_bool('ready_to_ship'),
        'made_to_order' => input_bool('made_to_order'),
        'lead_time_days' => max(0, min(120, input_int('lead_time_days'))),
        'is_customizable' => input_bool('is_customizable'),
        'custom_fields' => nullable(implode(',', array_intersect((array) ($_POST['custom_fields'] ?? []), ['measurements', 'color', 'personalization', 'instructions']))),
        'customization_fee' => (float) input_float('customization_fee', 0),
        'custom_garment_id' => input_int('custom_garment_id') && studio_garment(input_int('custom_garment_id'), false) ? input_int('custom_garment_id') : null,
        'allow_urgent' => input_bool('allow_urgent'),
        'is_featured' => input_bool('is_featured'),
        'is_new' => input_bool('is_new'),
        'is_bestseller' => input_bool('is_bestseller'),
        'badge' => nullable(mb_substr(input('badge', '', 'post'), 0, 40)),
        'status' => in_array(input('status', '', 'post'), ['draft', 'published', 'inactive'], true) ? input('status', '', 'post') : 'draft',
        'seo_title' => nullable(mb_substr(input('seo_title', '', 'post'), 0, 190)),
        'meta_description' => nullable(mb_substr(input('meta_description', '', 'post'), 0, 320)),
        'og_title' => nullable(mb_substr(input('og_title', '', 'post'), 0, 190)),
        'og_description' => nullable(mb_substr(input('og_description', '', 'post'), 0, 320)),
        'canonical_url' => nullable(filter_var(input('canonical_url', '', 'post'), FILTER_VALIDATE_URL) ?: ''),
        'noindex' => input_bool('noindex'),
    ];
    foreach (['brand', 'fabric', 'fit', 'pattern', 'sleeve_type', 'collar_type', 'waist_type', 'length_type', 'season', 'occasion'] as $col) {
        $d[$col] = nullable(mb_substr(input($col, '', 'post'), 0, $col === 'fabric' ? 120 : 80));
    }
    if ($canCost) {
        $d['cost_price'] = input_float('cost_price');
    }
    $slugIn = input('slug', '', 'post');
    $d['slug'] = unique_slug('products', $slugIn !== '' ? $slugIn : $d['name'], $id);

    if (mb_strlen($d['name']) < 2) $errors[] = 'Product name is required.';
    if ($d['sku'] === '') $errors[] = 'SKU is required.';
    elseif (db_val('SELECT id FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $id])) $errors[] = 'SKU is already used by another product.';
    if ($d['price'] <= 0) $errors[] = 'Regular price must be greater than zero.';
    if ($d['sale_price'] !== null && $d['sale_price'] >= $d['price']) $errors[] = 'Sale price must be lower than the regular price (leave empty for no sale).';
    if ($d['is_customizable'] && !$d['custom_fields']) $errors[] = 'Choose at least one customisation field, or turn customisation off.';

    // Variant rows
    $variantRows = [];
    $seenSku = [];
    $seenCombo = [];
    foreach ((array) ($_POST['v'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $opts = [];
        foreach ((array) ($row['opt'] ?? []) as $k => $v) {
            $k = mb_substr(trim((string) $k), 0, 40);
            $v = mb_substr(trim((string) $v), 0, 60);
            if ($k !== '' && $v !== '') $opts[$k] = $v;
        }
        if (!$opts) continue;
        $vsku = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', (string) ($row['sku'] ?? '')));
        if ($vsku === '') {
            $vsku = $d['sku'] . '-' . strtoupper(implode('-', array_map(fn($x) => preg_replace('/[^A-Za-z0-9]/', '', $x), $opts)));
        }
        $combo = json_encode($opts);
        if (isset($seenCombo[$combo])) { $errors[] = 'Duplicate variant: ' . variant_label($opts); continue; }
        if (isset($seenSku[$vsku])) { $errors[] = 'Duplicate variant SKU: ' . $vsku; continue; }
        $seenCombo[$combo] = $seenSku[$vsku] = true;
        $vp = is_numeric($row['price'] ?? null) && (float) $row['price'] > 0 ? round((float) $row['price'], 2) : null;
        $vs = is_numeric($row['sale_price'] ?? null) && (float) $row['sale_price'] > 0 ? round((float) $row['sale_price'], 2) : null;
        $variantRows[] = ['id' => (int) ($row['id'] ?? 0), 'options' => $opts, 'sku' => $vsku, 'price' => $vp, 'sale_price' => $vs,
            'stock' => (int) ($row['stock'] ?? 0), 'active' => !empty($row['active']) ? 1 : 0];
    }
    foreach ($variantRows as $vr) {
        $clash = db_val('SELECT v.id FROM product_variants v WHERE v.sku = ? AND v.product_id <> ?', [$vr['sku'], $id]);
        if ($clash || $vr['sku'] === $d['sku']) $errors[] = 'Variant SKU ' . $vr['sku'] . ' is already in use.';
    }
    $d['has_variants'] = $variantRows ? 1 : 0;

    if (!$errors) {
        try {
            $id = db_transaction(function () use ($d, $id, $product, $variantRows) {
                $simpleStock = input_int('stock_qty');
                if ($product) {
                    if ($product['slug'] !== $d['slug'] && $product['status'] === 'published') {
                        redirect_add('/product/' . $product['slug'], '/product/' . $d['slug']);
                    }
                    if ($d['status'] === 'published' && !$product['published_at']) {
                        $d['published_at'] = date('Y-m-d H:i:s');
                    }
                    db_update('products', $d, 'id = ?', [$id]);
                    if (!$d['has_variants'] && $simpleStock !== (int) $product['stock_qty']) {
                        inventory_adjust($id, null, $simpleStock - (int) $product['stock_qty'], 'manual', null, 'Edited on product form', admin_id());
                    }
                } else {
                    $d['stock_qty'] = 0;
                    if ($d['status'] === 'published') {
                        $d['published_at'] = date('Y-m-d H:i:s');
                    }
                    $id = db_insert('products', $d);
                    if (!$d['has_variants'] && $simpleStock) {
                        inventory_adjust($id, null, $simpleStock, 'initial', null, 'Opening stock', admin_id());
                    }
                }

                // Variants: update, create, remove
                $keep = [];
                foreach ($variantRows as $i => $vr) {
                    $existing = $vr['id'] ? db_row('SELECT * FROM product_variants WHERE id = ? AND product_id = ?', [$vr['id'], $id]) : null;
                    if (!$existing) {
                        $existing = db_row('SELECT * FROM product_variants WHERE sku = ? AND product_id = ?', [$vr['sku'], $id]);
                    }
                    if ($existing) {
                        db_update('product_variants', ['sku' => $vr['sku'], 'price' => $vr['price'], 'sale_price' => $vr['sale_price'], 'is_active' => $vr['active'], 'sort_order' => $i], 'id = ?', [(int) $existing['id']]);
                        if ($vr['stock'] !== (int) $existing['stock_qty']) {
                            inventory_adjust($id, (int) $existing['id'], $vr['stock'] - (int) $existing['stock_qty'], 'manual', null, 'Edited on product form', admin_id());
                        }
                        $vid = (int) $existing['id'];
                        db_exec('DELETE FROM product_variant_values WHERE variant_id = ?', [$vid]);
                    } else {
                        $vid = db_insert('product_variants', ['product_id' => $id, 'sku' => $vr['sku'], 'price' => $vr['price'], 'sale_price' => $vr['sale_price'], 'stock_qty' => 0, 'is_active' => $vr['active'], 'sort_order' => $i]);
                        if ($vr['stock']) {
                            inventory_adjust($id, $vid, $vr['stock'], 'initial', null, 'Opening stock', admin_id());
                        }
                    }
                    $o = 0;
                    foreach ($vr['options'] as $name => $val) {
                        db_insert('product_variant_values', ['variant_id' => $vid, 'option_name' => $name, 'option_value' => $val, 'sort_order' => $o++]);
                    }
                    $keep[] = $vid;
                }
                $old = db_col('SELECT id FROM product_variants WHERE product_id = ?' . ($keep ? ' AND id NOT IN (' . db_in($keep) . ')' : ''), array_merge([$id], $keep));
                foreach ($old as $oid) {
                    if (db_val('SELECT 1 FROM order_items WHERE variant_id = ? LIMIT 1', [(int) $oid]) || db_val('SELECT 1 FROM cart_items WHERE variant_id = ? LIMIT 1', [(int) $oid])) {
                        db_exec('UPDATE product_variants SET is_active = 0 WHERE id = ?', [(int) $oid]); // keep history
                        db_exec('DELETE FROM cart_items WHERE variant_id = ?', [(int) $oid]);
                    } else {
                        db_exec('DELETE FROM product_variants WHERE id = ?', [(int) $oid]);
                    }
                }

                // Attributes
                db_exec('DELETE FROM product_attributes WHERE product_id = ?', [$id]);
                foreach ((array) ($_POST['attr_name'] ?? []) as $i => $n) {
                    $n = mb_substr(trim((string) $n), 0, 80);
                    $v = mb_substr(trim((string) ($_POST['attr_value'][$i] ?? '')), 0, 255);
                    if ($n !== '' && $v !== '') {
                        db_insert('product_attributes', ['product_id' => $id, 'name' => $n, 'value' => $v, 'sort_order' => $i]);
                    }
                }

                // Relations & collections
                foreach (['related', 'cross_sell', 'upsell'] as $type) {
                    if (!empty($_POST['rel_' . $type . '_present'])) {
                        db_exec('DELETE FROM product_relations WHERE product_id = ? AND relation_type = ?', [$id, $type]);
                        foreach (a_ids('rel_' . $type) as $i => $rid) {
                            if ($rid !== $id) {
                                db_exec('INSERT IGNORE INTO product_relations (product_id, related_id, relation_type, sort_order) VALUES (?, ?, ?, ?)', [$id, $rid, $type, $i]);
                            }
                        }
                    }
                }
                if (!empty($_POST['collections_present'])) {
                    $cols = a_ids('collections');
                    db_exec('DELETE FROM collection_products WHERE product_id = ?' . ($cols ? ' AND collection_id NOT IN (' . db_in($cols) . ')' : ''), array_merge([$id], $cols));
                    foreach ($cols as $cid) {
                        db_exec('INSERT IGNORE INTO collection_products (collection_id, product_id, sort_order) VALUES (?, ?, 999)', [$cid, $id]);
                    }
                }

                // Existing images
                foreach ((array) ($_POST['img'] ?? []) as $imgId => $im) {
                    $imgId = (int) $imgId;
                    $row = db_row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
                    if (!$row) continue;
                    if (!empty($im['delete'])) {
                        delete_upload($row['image']);
                        db_exec('DELETE FROM product_images WHERE id = ?', [$imgId]);
                        continue;
                    }
                    $view = in_array($im['view_type'] ?? '', ['front', 'back', 'side', 'detail', 'lifestyle', 'other'], true) ? $im['view_type'] : 'front';
                    db_update('product_images', [
                        'alt_text' => nullable(mb_substr(trim((string) ($im['alt'] ?? '')), 0, 190)),
                        'caption' => nullable(mb_substr(trim((string) ($im['caption'] ?? '')), 0, 190)),
                        'view_type' => $view,
                        'color_name' => nullable(mb_substr(trim((string) ($im['color'] ?? '')), 0, 60)),
                        'sort_order' => (int) ($im['sort'] ?? 0),
                        'is_main' => (int) input_int('main_image') === $imgId ? 1 : 0,
                    ], 'id = ?', [$imgId]);
                }
                // New images: uploads + URLs
                $next = (int) db_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?', [$id]);
                $new = a_try(fn() => upload_images('new_images'), []);
                foreach (array_filter(array_map('trim', explode("\n", (string) ($_POST['image_urls'] ?? '')))) as $u) {
                    if (preg_match('#^https://#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) $new[] = $u;
                }
                foreach ($new as $path) {
                    db_insert('product_images', ['product_id' => $id, 'image' => $path, 'alt_text' => $d['name'], 'sort_order' => $next++, 'view_type' => 'front']);
                }
                if (!db_val('SELECT 1 FROM product_images WHERE product_id = ? AND is_main = 1', [$id])) {
                    db_exec('UPDATE product_images SET is_main = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
                }
                $ogImg = a_image_value('og_image', $product['og_image'] ?? null);
                db_exec('UPDATE products SET og_image = ? WHERE id = ?', [$ogImg, $id]);
                return $id;
            });
            audit_log($product ? 'product_updated' : 'product_created', 'product', $id, ['name' => $d['name'], 'status' => $d['status'], 'price' => $d['price'], 'sale_price' => $d['sale_price']]);
            flash('success', 'Product saved.');
            redirect('admin/products/edit.php?id=' . $id);
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $errors[] = 'The product could not be saved: ' . (APP_ENV === 'production' ? 'database error.' : $e->getMessage());
        }
    }
    $product = array_merge($product ?? [], $d, ['id' => $id]);
}

$p = $product ?? ['status' => 'draft', 'track_stock' => 1, 'low_stock_threshold' => 5, 'ready_to_ship' => 1, 'brand' => 'Urban Collections', 'allow_urgent' => 1];
$v = fn($k, $def = '') => $p[$k] ?? $def;
$images = $id ? db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$id]) : [];
$variants = $id ? product_variants($id, false) : [];
$attrs = $id ? db_all('SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort_order, id', [$id]) : [];
$rel = fn($t) => $id ? db_col('SELECT related_id FROM product_relations WHERE product_id = ? AND relation_type = ? ORDER BY sort_order', [$id, $t]) : [];
$colIds = $id ? db_col('SELECT collection_id FROM collection_products WHERE product_id = ?', [$id]) : [];
$optionNames = [];
foreach ($variants as $vv) {
    foreach (array_keys($vv['options']) as $n) $optionNames[$n] = true;
}
$sizesByGroup = [];
foreach (db_all('SELECT size_group, name FROM sizes ORDER BY sort_order, id') as $s) {
    $sizesByGroup[$s['size_group']][] = $s['name'];
}
$colorNames = db_col('SELECT name FROM colors ORDER BY sort_order, name');
$suggest = fn($col) => db_col("SELECT DISTINCT $col FROM products WHERE $col IS NOT NULL AND $col <> '' ORDER BY $col");
$simpleStock = (int) $v('stock_qty', 0);

admin_header($id ? 'Edit product' : 'Add product', ['editor' => true]);
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($id): ?>
<div class="d-flex gap-2 mb-3 small">
    <a href="<?= e(url('product/' . $v('slug'))) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View on store<?= $v('status') !== 'published' ? ' (admin preview)' : '' ?></a>
    <span class="text-muted">·</span><a href="<?= e(url('admin/inventory/movements.php?product=' . $id)) ?>">Stock history</a>
</div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" data-dirty-check>
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= (int) $id ?>">
<div class="row g-4">
<div class="col-xl-9">
<ul class="nav a-tabs" role="tablist">
    <?php foreach (['general' => 'General', 'images' => 'Images & Video', 'variants' => 'Sizes & Variants', 'details' => 'Details & Specs', 'custom' => 'Customisation', 'links' => 'Related & Collections', 'seo' => 'SEO'] as $k => $l): ?>
        <li class="nav-item"><a class="nav-link<?= $k === 'general' ? ' active' : '' ?>" data-bs-toggle="tab" href="#t-<?= $k ?>"><?= $l ?></a></li>
    <?php endforeach; ?>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="t-general">
    <div class="card a-card"><div class="card-body">
        <?= a_input('name', 'Product name *', $v('name'), 'text', ['required' => true, 'data-slug-source' => $id ? false : '#f_slug']) ?>
        <div class="row">
            <div class="col-md-6"><?= a_input('slug', 'URL slug', $v('slug'), 'text', ['data-slug-target' => true], 'Store URL: /product/<b>slug</b>. Changing it on a published product adds a 301 redirect automatically.') ?></div>
            <div class="col-md-6"><?= a_input('sku', 'SKU *', $v('sku'), 'text', ['required' => true]) ?></div>
        </div>
        <?php
        $catOpts = ['' => '— Choose category —'];
        foreach (category_options(false) as $o) $catOpts[$o['id']] = $o['label'];
        echo a_select('category_id', 'Category / subcategory', $catOpts, $v('category_id'), [], 'Choose the most specific subcategory. Products also appear in every parent category.');
        ?>
        <?= a_textarea('short_description', 'Short description', $v('short_description'), 2, ['maxlength' => 600]) ?>
        <?= a_html('description', 'Full description', $v('description')) ?>
    </div></div>
    <div class="card a-card"><div class="card-header">Pricing</div><div class="card-body"><div class="row">
        <div class="col-md-4"><?= a_input('price', 'Regular price *', $v('price'), 'number', ['step' => '0.01', 'min' => '0', 'required' => true]) ?></div>
        <div class="col-md-4"><?= a_input('sale_price', 'Sale price', $v('sale_price'), 'number', ['step' => '0.01', 'min' => '0'], 'Leave empty when not on sale.') ?></div>
        <?php if ($canCost): ?><div class="col-md-4"><?= a_input('cost_price', 'Cost price (private)', $v('cost_price'), 'number', ['step' => '0.01', 'min' => '0'], 'Visible only to authorised admins.') ?></div><?php endif; ?>
    </div></div></div>
    <div class="card a-card"><div class="card-header">Inventory</div><div class="card-body"><div class="row">
        <div class="col-md-4" id="simpleStockBox"<?= $variants ? ' hidden' : '' ?>><?= a_input('stock_qty', 'Stock quantity', $simpleStock, 'number', ['step' => 1]) ?>
            <?php if ($variants): ?><p class="small text-muted">Stock is tracked per variant.</p><?php endif; ?></div>
        <div class="col-md-4"><?= a_input('low_stock_threshold', 'Low-stock alert at', $v('low_stock_threshold', 5), 'number', ['min' => 0]) ?></div>
        <div class="col-md-4"><?= a_input('weight_kg', 'Weight (kg)', $v('weight_kg'), 'number', ['step' => '0.001', 'min' => 0]) ?></div>
        <div class="col-md-4"><?= a_check('track_stock', 'Track stock', (int) $v('track_stock', 1)) ?></div>
        <div class="col-md-4"><?= a_check('allow_backorder', 'Allow backorders (sell when out of stock)', (int) $v('allow_backorder')) ?></div>
        <div class="col-md-4"><?= a_input('dimensions', 'Dimensions', $v('dimensions'), 'text', ['placeholder' => 'e.g. 30 × 20 × 10 cm']) ?></div>
    </div><?php if ($variants): ?><p class="small text-muted mb-0">This product has variants — stock is set per variant in the “Sizes &amp; Variants” tab.</p><?php endif; ?></div></div>
</div>

<div class="tab-pane fade" id="t-images">
    <div class="card a-card"><div class="card-body">
        <?php if ($images): ?>
            <div class="a-img-grid mb-4">
                <?php foreach ($images as $im): $iid = (int) $im['id']; ?>
                    <div class="a-img-card<?= (int) $im['is_main'] ? ' is-main' : '' ?>">
                        <img src="<?= e(img_sized($im['image'], 400)) ?>" alt="" loading="lazy">
                        <div class="form-check small mb-1"><input class="form-check-input" type="radio" name="main_image" value="<?= $iid ?>" id="main<?= $iid ?>"<?= (int) $im['is_main'] ? ' checked' : '' ?>><label class="form-check-label" for="main<?= $iid ?>">Main image</label></div>
                        <input class="form-control form-control-sm mb-1" name="img[<?= $iid ?>][alt]" value="<?= e($im['alt_text']) ?>" placeholder="Alt text (SEO / accessibility)">
                        <input class="form-control form-control-sm mb-1" name="img[<?= $iid ?>][caption]" value="<?= e($im['caption']) ?>" placeholder="Caption (optional)">
                        <div class="d-flex gap-1 mb-1">
                            <select class="form-select form-select-sm" name="img[<?= $iid ?>][view_type]"><?php foreach (['front', 'back', 'side', 'detail', 'lifestyle', 'other'] as $vt): ?><option<?= $im['view_type'] === $vt ? ' selected' : '' ?>><?= $vt ?></option><?php endforeach; ?></select>
                            <input class="form-control form-control-sm" style="max-width:64px" type="number" name="img[<?= $iid ?>][sort]" value="<?= (int) $im['sort_order'] ?>" title="Order">
                        </div>
                        <input class="form-control form-control-sm mb-1" name="img[<?= $iid ?>][color]" value="<?= e($im['color_name']) ?>" placeholder="Show for colour (optional)" list="colorList">
                        <div class="form-check small"><input class="form-check-input" type="checkbox" name="img[<?= $iid ?>][delete]" value="1" id="del<?= $iid ?>"><label class="form-check-label text-danger" for="del<?= $iid ?>">Delete</label></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="mb-3"><label class="form-label">Upload images</label><input class="form-control" type="file" name="new_images[]" multiple accept="image/jpeg,image/png,image/webp">
            <div class="form-text">JPG, PNG or WebP up to <?= (int) UPLOAD_MAX_MB ?> MB each. Images are resized to 2000px and converted to WebP. Use a consistent 3:4 portrait ratio for the best look.</div></div>
        <?= a_textarea('image_urls', 'Or add images by URL (one https:// URL per line)', '', 2) ?>
        <?= a_input('video_url', 'Product video URL (YouTube, Vimeo or .mp4)', $v('video_url'), 'url') ?>
        <datalist id="colorList"><?php foreach ($colorNames as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    </div></div>
</div>

<div class="tab-pane fade" id="t-variants">
    <div class="card a-card"><div class="card-body">
        <p class="small text-muted">Add the options this product comes in (e.g. <b>Color</b> + <b>Size</b>, or <b>Waist</b> + <b>Length</b> for jeans, or <b>Shoe Size</b>). Customers must choose every option before adding to bag. Leave empty for a simple product with one stock count.</p>
        <div id="optionBuilder">
            <?php foreach (array_keys($optionNames) ?: [] as $n):
                $vals = array_values(array_unique(array_map(fn($x) => $x['options'][$n] ?? null, $variants))); ?>
                <div class="row g-2 mb-2 opt-row"><div class="col-md-3"><input class="form-control" data-opt-name value="<?= e($n) ?>" list="optNames"></div>
                    <div class="col-md-8"><input class="form-control" data-opt-values value="<?= e(implode(', ', array_filter($vals))) ?>" placeholder="Comma-separated values"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-light w-100" data-opt-remove><i class="bi bi-x"></i></button></div></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-dark" data-opt-add><i class="bi bi-plus"></i> Add option</button>
            <?php foreach ($sizesByGroup as $g => $vals): ?>
                <button type="button" class="btn btn-sm btn-light" data-opt-preset="<?= e($g === 'Apparel' ? 'Size' : ($g === 'Shoe' ? 'Shoe Size' : ($g === 'Other' ? 'Size' : $g))) ?>" data-values="<?= e(implode(', ', $vals)) ?>">+ <?= e($g) ?> sizes</button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-light" data-opt-preset="Color" data-values="Black, White, Navy">+ Colours</button>
            <button type="button" class="btn btn-sm btn-dark ms-auto" data-opt-generate><i class="bi bi-magic"></i> Generate / update variant rows</button>
        </div>
        <datalist id="optNames"><option value="Color"><option value="Size"><option value="Waist"><option value="Length"><option value="Shoe Size"></datalist>
        <div class="table-responsive"><table class="table a-table a-variant-table mb-0"><thead><tr><th>Variant</th><th>SKU</th><th>Price override</th><th>Sale override</th><th>Stock</th><th>Active</th><th></th></tr></thead>
            <tbody id="variantRows">
            <?php foreach ($variants as $i => $vv): ?>
                <tr data-combo="<?= e(json_encode($vv['options'])) ?>">
                    <td class="small"><input type="hidden" name="v[<?= $i ?>][id]" value="<?= (int) $vv['id'] ?>">
                        <?php foreach ($vv['options'] as $on => $ov): ?><input type="hidden" name="v[<?= $i ?>][opt][<?= e($on) ?>]" value="<?= e($ov) ?>"><?php endforeach; ?>
                        <?= e(variant_label($vv['options'])) ?></td>
                    <td><input class="form-control form-control-sm" name="v[<?= $i ?>][sku]" value="<?= e($vv['sku']) ?>"></td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[<?= $i ?>][price]" value="<?= e($vv['price']) ?>" placeholder="—"></td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[<?= $i ?>][sale_price]" value="<?= e($vv['sale_price']) ?>" placeholder="—"></td>
                    <td><input class="form-control form-control-sm" type="number" name="v[<?= $i ?>][stock]" value="<?= (int) $vv['stock_qty'] ?>"></td>
                    <td><input class="form-check-input" type="checkbox" name="v[<?= $i ?>][active]" value="1"<?= (int) $vv['is_active'] ? ' checked' : '' ?>></td>
                    <td><button type="button" class="btn btn-sm btn-light text-danger" data-row-remove><i class="bi bi-trash"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p class="small text-muted mt-2 mb-0">Price overrides are optional — leave blank to use the product price. Stock changes are recorded in the inventory history. Variants that appear in past orders are deactivated rather than deleted.</p>
    </div></div>
</div>

<div class="tab-pane fade" id="t-details">
    <div class="card a-card"><div class="card-body"><div class="row">
        <?php foreach (['brand' => 'Brand', 'fabric' => 'Fabric / material', 'fit' => 'Fit', 'pattern' => 'Pattern', 'sleeve_type' => 'Sleeve type', 'collar_type' => 'Collar type', 'waist_type' => 'Waist type', 'length_type' => 'Length', 'season' => 'Season', 'occasion' => 'Occasion'] as $col => $label): ?>
            <div class="col-md-4"><?= a_input($col, $label, $v($col), 'text', ['list' => 'dl_' . $col]) ?><datalist id="dl_<?= $col ?>"><?php foreach ($suggest($col) as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
        <?php endforeach; ?>
        </div>
        <p class="small text-muted">Fit, fabric, pattern, occasion, season and brand power the storefront filters.</p>
        <?= a_textarea('care_instructions', 'Care instructions', $v('care_instructions'), 3) ?>
        <label class="form-label">Additional specifications</label>
        <div id="attrRows">
            <?php foreach (array_merge($attrs, [['name' => '', 'value' => '']]) as $a): ?>
                <div class="row g-2 mb-2"><div class="col-5"><input class="form-control form-control-sm" name="attr_name[]" value="<?= e($a['name']) ?>" placeholder="e.g. Origin"></div><div class="col-6"><input class="form-control form-control-sm" name="attr_value[]" value="<?= e($a['value']) ?>" placeholder="e.g. Made in Pakistan"></div><div class="col-1"><button type="button" class="btn btn-sm btn-light" data-row-remove><i class="bi bi-x"></i></button></div></div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-dark mb-3" data-attr-add><i class="bi bi-plus"></i> Add specification</button>
        <?= a_html('size_guide', 'Product-specific size guide (optional — otherwise the store size guide page is shown)', $v('size_guide')) ?>
    </div></div>
</div>

<div class="tab-pane fade" id="t-custom">
    <div class="card a-card"><div class="card-body">
        <div class="row">
            <div class="col-md-4"><?= a_check('ready_to_ship', 'Ready to ship', (int) $v('ready_to_ship', 1)) ?></div>
            <div class="col-md-4"><?= a_check('made_to_order', 'Made to order (always available, ships after lead time)', (int) $v('made_to_order')) ?></div>
            <div class="col-md-4"><?= a_input('lead_time_days', 'Production lead time (days)', $v('lead_time_days', 0), 'number', ['min' => 0, 'max' => 120]) ?></div>
        </div>
        <?= a_check('allow_urgent', 'Allow urgent (city-wise) delivery for this product', (int) $v('allow_urgent', 1), 'Urgent rates are set under Delivery & Shipping. Never offered for made-to-order or custom-size items.') ?>
        <hr>
        <h3 class="h6">Custom Size Studio</h3>
        <?php $gOpts = ['' => '— Not available —']; foreach (studio_garments(false) as $sg) { $gOpts[$sg['id']] = $sg['name'] . ((int) $sg['is_active'] ? '' : ' (garment switched off)'); } ?>
        <?= a_select('custom_garment_id', 'Let customers order this design in their own measurements as', $gOpts, $v('custom_garment_id'), [], 'Adds a “Get it in your exact size” button and the interactive mannequin. Charges, size charts and style options: <a href="' . e(url('admin/studio/')) . '">Custom Size Studio</a>.') ?>
        <hr>
        <h3 class="h6">Simple personalisation</h3>
        <?= a_check('is_customizable', 'Offer customisation on this product', (int) $v('is_customizable')) ?>
        <?php $cf = array_filter(explode(',', (string) $v('custom_fields'))); ?>
        <div class="mb-3"><label class="form-label">Fields shown to customers</label><div class="d-flex flex-wrap gap-3">
            <?php foreach (['measurements' => 'Custom size / measurements', 'color' => 'Custom colour', 'personalization' => 'Personalisation (initials)', 'instructions' => 'Special instructions'] as $k => $l): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="custom_fields[]" value="<?= $k ?>" id="cf_<?= $k ?>"<?= in_array($k, $cf, true) ? ' checked' : '' ?>><label class="form-check-label" for="cf_<?= $k ?>"><?= $l ?></label></div>
            <?php endforeach; ?></div></div>
        <div class="col-md-4"><?= a_input('customization_fee', 'Customisation charge (per item)', $v('customization_fee', 0), 'number', ['step' => '0.01', 'min' => 0]) ?></div>
        <p class="small text-muted mb-0">Customised order lines are flagged on the order for confirmation, and the production lead time is added to the delivery estimate.</p>
    </div></div>
</div>

<div class="tab-pane fade" id="t-links">
    <div class="card a-card"><div class="card-body">
        <?= a_picker('collections', 'Collections', 'collections', $colIds) ?>
        <?= a_picker('rel_related', 'Related products', 'products', $rel('related'), 'Shown under “Related Products”. Same-category products fill remaining slots automatically.') ?>
        <?= a_picker('rel_cross_sell', 'Cross-sells (complete the look)', 'products', $rel('cross_sell'), 'Used in “Frequently Bought Together” until real purchase data exists, and on the bag page.') ?>
        <?= a_picker('rel_upsell', 'Upsells', 'products', $rel('upsell'), 'Premium alternatives shown on the product page.') ?>
    </div></div>
</div>

<div class="tab-pane fade" id="t-seo">
    <div class="card a-card"><div class="card-body">
        <?= a_input('seo_title', 'SEO title', $v('seo_title'), 'text', ['maxlength' => 190], 'Defaults to the product name.') ?>
        <?= a_textarea('meta_description', 'Meta description', $v('meta_description'), 2, ['maxlength' => 320], 'Aim for 140–160 characters.') ?>
        <div class="row"><div class="col-md-6"><?= a_input('og_title', 'Open Graph title', $v('og_title')) ?></div><div class="col-md-6"><?= a_input('canonical_url', 'Canonical URL (only if different)', $v('canonical_url'), 'url') ?></div></div>
        <?= a_textarea('og_description', 'Open Graph description', $v('og_description'), 2) ?>
        <?= a_image('og_image', 'Open Graph image (defaults to main product image)', $v('og_image')) ?>
        <?= a_check('noindex', 'Hide from search engines (noindex)', (int) $v('noindex')) ?>
    </div></div>
</div>
</div>
</div>

<div class="col-xl-3">
    <div class="card a-card"><div class="card-header">Publish</div><div class="card-body">
        <?= a_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft', 'inactive' => 'Inactive'], $v('status', 'draft')) ?>
        <button class="btn btn-dark w-100"><i class="bi bi-check2"></i> Save product</button>
    </div></div>
    <div class="card a-card"><div class="card-header">Merchandising</div><div class="card-body">
        <?= a_check('is_featured', 'Featured', (int) $v('is_featured')) ?>
        <?= a_check('is_new', 'New arrival', (int) $v('is_new')) ?>
        <?= a_check('is_bestseller', 'Best seller', (int) $v('is_bestseller')) ?>
        <?= a_input('badge', 'Badge label', $v('badge'), 'text', ['list' => 'badgeList'], 'Overrides automatic badges (Sale, Best Seller, New Arrival). Leave empty for automatic.') ?>
        <datalist id="badgeList"><?php foreach (['New Arrival', 'Trending', 'Limited Edition', 'Premium', 'Best Seller', 'Popular', 'Sale', 'Made to Order'] as $b): ?><option value="<?= $b ?>"><?php endforeach; ?></datalist>
    </div></div>
</div>
</div>
</form>

<template id="optTpl"><div class="row g-2 mb-2 opt-row"><div class="col-md-3"><input class="form-control" data-opt-name placeholder="Option name (e.g. Size)" list="optNames"></div><div class="col-md-8"><input class="form-control" data-opt-values placeholder="Comma-separated values, e.g. S, M, L, XL"></div><div class="col-md-1"><button type="button" class="btn btn-light w-100" data-opt-remove><i class="bi bi-x"></i></button></div></div></template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = window.jQuery, base = <?= ejs($v('sku', 'SKU')) ?>;
    var esc = function (s) { return $('<i>').text(s).html(); };
    $(document).on('click', '[data-opt-add]', function () { $('#optionBuilder').append($('#optTpl').html()); });
    $(document).on('click', '[data-opt-remove]', function () { $(this).closest('.opt-row').remove(); });
    $(document).on('click', '[data-opt-preset]', function () {
        var $r = $($('#optTpl').html());
        $r.find('[data-opt-name]').val($(this).data('opt-preset'));
        $r.find('[data-opt-values]').val($(this).data('values'));
        $('#optionBuilder').append($r);
    });
    $(document).on('click', '[data-row-remove]', function () { $(this).closest('tr, .row').remove(); });
    $(document).on('click', '[data-attr-add]', function () {
        $('#attrRows').append('<div class="row g-2 mb-2"><div class="col-5"><input class="form-control form-control-sm" name="attr_name[]"></div><div class="col-6"><input class="form-control form-control-sm" name="attr_value[]"></div><div class="col-1"><button type="button" class="btn btn-sm btn-light" data-row-remove><i class="bi bi-x"></i></button></div></div>');
    });
    $(document).on('click', '[data-opt-generate]', function () {
        var opts = [];
        $('#optionBuilder .opt-row').each(function () {
            var n = $.trim($(this).find('[data-opt-name]').val());
            var vals = $(this).find('[data-opt-values]').val().split(',').map(function (s) { return $.trim(s); }).filter(Boolean);
            if (n && vals.length) { opts.push({ name: n, values: vals }); }
        });
        if (!opts.length) { Swal.fire({ text: 'Add at least one option with values first.', icon: 'info' }); return; }
        var combos = [{}];
        opts.forEach(function (o) {
            var next = [];
            combos.forEach(function (c) { o.values.forEach(function (v) { var x = $.extend({}, c); x[o.name] = v; next.push(x); }); });
            combos = next;
        });
        if (combos.length > 300) { Swal.fire({ text: 'That would create ' + combos.length + ' variants. Please reduce the options (max 300).', icon: 'warning' }); return; }
        var existing = {};
        $('#variantRows tr').each(function () { existing[JSON.stringify($(this).data('combo'))] = this; });
        var sku = ($('#f_sku').val() || base).toUpperCase();
        var $tbody = $('#variantRows'), rows = [];
        combos.forEach(function (c, i) {
            var key = JSON.stringify(c);
            if (existing[key]) { rows.push(existing[key]); return; }
            var idx = 'n' + Date.now() + i;
            var hidden = Object.keys(c).map(function (k) { return '<input type="hidden" name="v[' + idx + '][opt][' + esc(k) + ']" value="' + esc(c[k]) + '">'; }).join('');
            var label = Object.keys(c).map(function (k) { return esc(k) + ': ' + esc(c[k]); }).join(' / ');
            var vsku = sku + '-' + Object.keys(c).map(function (k) { return String(c[k]).replace(/[^A-Za-z0-9]/g, '').toUpperCase(); }).join('-');
            var $tr = $('<tr>').attr('data-combo', key).html('<td class="small">' + hidden + label + '</td><td><input class="form-control form-control-sm" name="v[' + idx + '][sku]" value="' + esc(vsku) + '"></td>' +
                '<td><input class="form-control form-control-sm" type="number" step="0.01" name="v[' + idx + '][price]" placeholder="—"></td><td><input class="form-control form-control-sm" type="number" step="0.01" name="v[' + idx + '][sale_price]" placeholder="—"></td>' +
                '<td><input class="form-control form-control-sm" type="number" name="v[' + idx + '][stock]" value="0"></td><td><input class="form-check-input" type="checkbox" name="v[' + idx + '][active]" value="1" checked></td>' +
                '<td><button type="button" class="btn btn-sm btn-light text-danger" data-row-remove><i class="bi bi-trash"></i></button></td>');
            rows.push($tr[0]);
        });
        var removed = Object.keys(existing).length - rows.filter(function (r) { return $(r).find('input[name$="[id]"]').length; }).length;
        $tbody.empty().append(rows);
        $('#simpleStockBox').prop('hidden', true);
        Swal.fire({ toast: true, position: 'bottom-end', timer: 3500, showConfirmButton: false, icon: 'success', title: combos.length + ' variant rows ready' + (removed > 0 ? ' (' + removed + ' old rows removed)' : '') + '. Set stock, then save.' });
    });
});
</script>
<?php admin_footer(['editor' => true]);
