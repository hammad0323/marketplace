<?php
/** Create / edit a product: details, pricing, variants & stock, images, craft details, customisation, SEO, relations. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('products.manage');
$canCost = can('products.cost_view');

$id = (int)get('id');
$p = $id ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('danger', 'Product not found.'); redirect(admin_url('products')); }
$errors = [];

$attrs = db_all('SELECT * FROM attributes ORDER BY sort_order');
$attrVals = [];
foreach (db_all('SELECT * FROM attribute_values ORDER BY sort_order, id') as $v) $attrVals[(int)$v['attribute_id']][] = $v;
$attrByCode = [];
foreach ($attrs as $a) $attrByCode[$a['code']] = $a;
$sizeVals = $attrVals[(int)($attrByCode['size']['id'] ?? 0)] ?? [];
$colorVals = $attrVals[(int)($attrByCode['color']['id'] ?? 0)] ?? [];

$text = ['name', 'sku', 'short_description', 'video_url', 'dimensions', 'fabric', 'material_details', 'embroidery_type', 'crochet_details', 'sleeve_design', 'neckline_design',
         'abaya_length', 'fit_silhouette', 'lining_info', 'transparency_info', 'care_instructions', 'model_info', 'dispatch_time', 'custom_sleeve_options', 'custom_terms',
         'seo_title', 'meta_description', 'og_title', 'og_description', 'canonical_url'];
$flags = ['track_inventory', 'allow_customization', 'custom_length_enabled', 'custom_sleeve_enabled', 'custom_notes_enabled', 'is_featured', 'is_new_arrival', 'is_best_seller', 'is_popular', 'is_limited', 'is_handcrafted', 'noindex'];

if (is_post()) {
    csrf_check();
    $d = [];
    foreach ($text as $k) $d[$k] = mb_substr(trim((string)post($k)), 0, in_array($k, ['material_details', 'care_instructions'], true) ? 5000 : 600);
    foreach ($flags as $k) $d[$k] = post($k) ? 1 : 0;
    $d['description'] = sanitize_html((string)($_POST['description'] ?? ''));
    $d['slug'] = slugify(post('slug') ?: $d['name']);
    $d['regular_price'] = to_money(post('regular_price'));
    $d['sale_price'] = to_money(post('sale_price'));
    $d['category_id'] = (int)post('category_id') ?: null;
    $d['subcategory_id'] = (int)post('subcategory_id') ?: null;
    $d['size_guide_id'] = (int)post('size_guide_id') ?: null;
    $d['low_stock_threshold'] = max(0, (int)post('low_stock_threshold'));
    $d['weight_grams'] = post('weight_grams') !== '' ? max(0, (int)post('weight_grams')) : null;
    $d['fulfillment_type'] = in_list(post('fulfillment_type'), ['ready_to_ship', 'made_to_order'], 'ready_to_ship');
    $d['production_lead_days'] = clamp_int(post('production_lead_days'), 0, 365);
    $d['custom_length_min'] = post('custom_length_min') !== '' ? clamp_int(post('custom_length_min'), 30, 80) : null;
    $d['custom_length_max'] = post('custom_length_max') !== '' ? clamp_int(post('custom_length_max'), 30, 80) : null;
    $d['customization_fee'] = to_money(post('customization_fee')) ?? 0;
    $d['status'] = in_list(post('status'), ['draft', 'published', 'inactive'], 'draft');
    $d['video_url'] = clean_url($d['video_url']);
    $d['canonical_url'] = clean_url($d['canonical_url']);
    if ($canCost) $d['cost_price'] = to_money(post('cost_price'));

    if (!v_len($d['name'], 2, 190)) $errors[] = 'Product name is required.';
    if (!preg_match('/^[A-Za-z0-9\-_.]{2,80}$/', $d['sku'])) $errors[] = 'SKU must be 2–80 letters, numbers, dashes or dots.';
    elseif (db_val('SELECT id FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $id])) $errors[] = 'Another product already uses this SKU.';
    if ($d['regular_price'] === null || $d['regular_price'] <= 0) $errors[] = 'Enter a regular price greater than zero.';
    if ($d['sale_price'] !== null && $d['sale_price'] >= $d['regular_price']) $errors[] = 'Sale price must be lower than the regular price (leave blank for no sale).';
    if (db_val('SELECT id FROM products WHERE slug = ? AND id <> ?', [$d['slug'], $id])) $errors[] = 'This URL slug is already used by another product.';
    if ($d['custom_length_min'] && $d['custom_length_max'] && $d['custom_length_min'] > $d['custom_length_max']) $errors[] = 'Custom length minimum must be below the maximum.';
    if ($d['subcategory_id'] && $d['category_id'] && !in_array($d['subcategory_id'], category_descendant_ids($d['category_id'], false), true)) $errors[] = 'The subcategory must belong to the selected category.';

    // Variant rows: v[key][...]
    $vrows = [];
    foreach ((array)($_POST['v'] ?? []) as $key => $row) {
        if (!is_array($row) || !empty($row['delete'])) { if (!empty($row['id'])) $vrows[] = ['delete' => (int)$row['id']]; continue; }
        $sku = trim((string)($row['sku'] ?? ''));
        if ($sku === '') continue;
        if (!preg_match('/^[A-Za-z0-9\-_.]{2,100}$/', $sku)) { $errors[] = "Variant SKU “{$sku}” is invalid."; continue; }
        $vrows[] = [
            'id' => (int)($row['id'] ?? 0), 'sku' => $sku,
            'size' => (int)($row['size'] ?? 0) ?: null, 'color' => (int)($row['color'] ?? 0) ?: null,
            'price' => to_money($row['price'] ?? ''), 'sale' => to_money($row['sale'] ?? ''),
            'stock' => (int)($row['stock'] ?? 0), 'status' => ($row['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }
    $skus = array_filter(array_column($vrows, 'sku'));
    if (count($skus) !== count(array_unique($skus))) $errors[] = 'Variant SKUs must be unique.';
    foreach ($vrows as $vr) {
        if (empty($vr['sku'])) continue;
        if (db_val('SELECT id FROM product_variants WHERE sku = ? AND id <> ?', [$vr['sku'], $vr['id']])) $errors[] = "Variant SKU {$vr['sku']} is already used.";
    }
    $combos = array_map(fn($v) => ($v['size'] ?? 0) . '-' . ($v['color'] ?? 0), array_filter($vrows, fn($v) => empty($v['delete'])));
    if (count($combos) !== count(array_unique($combos))) $errors[] = 'Two variants have the same size and colour.';

    if (!$errors) {
        try {
            $id = db_tx(function () use ($d, $id, $p, $vrows, $attrs) {
                if ($d['status'] === 'published' && (!$p || !$p['published_at'])) $d['published_at'] = now();
                if ($id) {
                    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
                    db_exec("UPDATE products SET $sets WHERE id = ?", array_merge(array_values($d), [$id]));
                    if ($p['slug'] !== $d['slug']) redirect_add_auto('/product/' . $p['slug'], '/product/' . $d['slug']);
                } else {
                    $cols = array_keys($d);
                    $id = db_insert('INSERT INTO products (' . implode(',', $cols) . ') VALUES (' . db_in($cols) . ')', array_values($d));
                }
                // Extra categories
                db_exec('DELETE FROM product_categories WHERE product_id = ?', [$id]);
                foreach (array_unique(array_map('intval', (array)($_POST['extra_categories'] ?? []))) as $c) {
                    if ($c) db_exec('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)', [$id, $c]);
                }
                // Facets
                db_exec('DELETE FROM product_attribute_values WHERE product_id = ?', [$id]);
                foreach (array_unique(array_map('intval', (array)($_POST['facets'] ?? []))) as $v) {
                    if ($v && db_val('SELECT av.id FROM attribute_values av JOIN attributes a ON a.id = av.attribute_id WHERE av.id = ? AND a.is_variant = 0', [$v])) {
                        db_exec('INSERT INTO product_attribute_values (product_id, attribute_value_id) VALUES (?, ?)', [$id, $v]);
                    }
                }
                // Relations
                db_exec('DELETE FROM product_relations WHERE product_id = ?', [$id]);
                foreach (['related', 'cross_sell', 'upsell'] as $type) {
                    foreach (array_values(array_unique(array_map('intval', (array)($_POST['rel_' . $type] ?? [])))) as $i => $rid) {
                        if ($rid && $rid !== $id) db_exec('INSERT IGNORE INTO product_relations (product_id, related_id, relation_type, sort_order) VALUES (?, ?, ?, ?)', [$id, $rid, $type, $i]);
                    }
                }
                // Variants
                $sizeAttr = (int)db_val("SELECT id FROM attributes WHERE code = 'size'");
                $colorAttr = (int)db_val("SELECT id FROM attributes WHERE code = 'color'");
                $sort = 0;
                foreach ($vrows as $vr) {
                    if (!empty($vr['delete'])) {
                        if (db_val('SELECT id FROM order_items WHERE variant_id = ? LIMIT 1', [$vr['delete']])) {
                            db_exec("UPDATE product_variants SET status = 'inactive' WHERE id = ? AND product_id = ?", [$vr['delete'], $id]);
                        } else {
                            db_exec('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$vr['delete'], $id]);
                        }
                        continue;
                    }
                    if ($vr['id'] && db_val('SELECT id FROM product_variants WHERE id = ? AND product_id = ?', [$vr['id'], $id])) {
                        db_exec('UPDATE product_variants SET sku = ?, price_override = ?, sale_price_override = ?, status = ?, sort_order = ? WHERE id = ?',
                            [$vr['sku'], $vr['price'], $vr['sale'], $vr['status'], $sort++, $vr['id']]);
                        $vid = $vr['id'];
                    } else {
                        $vid = db_insert('INSERT INTO product_variants (product_id, sku, price_override, sale_price_override, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                            [$id, $vr['sku'], $vr['price'], $vr['sale'], $vr['status'], $sort++]);
                    }
                    db_exec('DELETE FROM product_variant_values WHERE variant_id = ?', [$vid]);
                    foreach ([$vr['size'], $vr['color']] as $val) {
                        if ($val && db_val('SELECT id FROM attribute_values WHERE id = ? AND attribute_id IN (?, ?)', [$val, $sizeAttr, $colorAttr])) {
                            db_exec('INSERT INTO product_variant_values (variant_id, attribute_value_id) VALUES (?, ?)', [$vid, $val]);
                        }
                    }
                    $cur = db_val('SELECT quantity FROM product_inventory WHERE variant_id = ? FOR UPDATE', [$vid]);
                    if ($cur === null) {
                        db_exec('INSERT INTO product_inventory (variant_id, quantity) VALUES (?, 0)', [$vid]);
                        if ($vr['stock'] != 0) inventory_change($vid, $vr['stock'], 'initial', 'product', $id, 'Initial stock');
                    } elseif ((int)$cur !== $vr['stock'] && can('inventory.manage')) {
                        inventory_change($vid, $vr['stock'] - (int)$cur, 'adjustment', 'product', $id, 'Edited on product form');
                    }
                }
                // Every product needs at least one variant.
                if (!db_val('SELECT id FROM product_variants WHERE product_id = ?', [$id])) {
                    $vid = db_insert('INSERT INTO product_variants (product_id, sku, is_default) VALUES (?, ?, 1)', [$id, $d['sku'] . '-STD']);
                    db_exec('INSERT INTO product_inventory (variant_id, quantity) VALUES (?, 0)', [$vid]);
                }
                // Images: update existing
                foreach ((array)($_POST['img'] ?? []) as $imgId => $im) {
                    $imgId = (int)$imgId;
                    if (!empty($im['delete'])) {
                        $path = db_val('SELECT path FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
                        db_exec('DELETE FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
                        if ($path && !db_val('SELECT id FROM product_images WHERE path = ?', [$path])) upload_delete($path);
                        continue;
                    }
                    db_exec('UPDATE product_images SET alt_text = ?, caption = ?, view_type = ?, color_value_id = ?, sort_order = ?, is_main = ? WHERE id = ? AND product_id = ?', [
                        mb_substr(trim((string)($im['alt'] ?? '')), 0, 255) ?: null, mb_substr(trim((string)($im['caption'] ?? '')), 0, 255) ?: null,
                        in_list($im['view'] ?? 'other', ['front', 'back', 'side', 'detail', 'lifestyle', 'other'], 'other'), (int)($im['color'] ?? 0) ?: null,
                        (int)($im['sort'] ?? 0), (int)post('main_image') === $imgId ? 1 : 0, $imgId, $id]);
                }
                $sortBase = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id = ?', [$id]);
                foreach (upload_files_list('new_images') as $i => $file) {
                    $path = upload_image($file, 'products');
                    db_insert('INSERT INTO product_images (product_id, path, alt_text, sort_order) VALUES (?, ?, ?, ?)', [$id, $path, $d['name'], $sortBase + $i + 1]);
                }
                if (!db_val('SELECT id FROM product_images WHERE product_id = ? AND is_main = 1', [$id])) {
                    db_exec('UPDATE product_images SET is_main = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
                }
                $og = upload_optional('og_image_file', 'products');
                if ($og) db_exec('UPDATE products SET og_image = ? WHERE id = ?', [$og, $id]);
                elseif (post('remove_og_image')) db_exec('UPDATE products SET og_image = NULL WHERE id = ?', [$id]);
                return $id;
            });
            audit($p ? 'product_update' : 'product_create', 'product', $id, ['name' => $d['name'], 'status' => $d['status']]);
            flash('success', 'Product saved.');
            redirect(admin_url('product-edit?id=' . $id));
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
    $p = array_merge($p ?? [], $d, ['id' => $id]);
}

$p = $p ?? ['id' => 0, 'status' => 'draft', 'track_inventory' => 1, 'low_stock_threshold' => (int)setting('low_stock_default', 3), 'fulfillment_type' => 'ready_to_ship', 'production_lead_days' => 0, 'customization_fee' => 0, 'is_handcrafted' => 1];
$v = fn($k, $def = '') => $p[$k] ?? $def;
$images = $id ? db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$id]) : [];
$variants = $id ? db_all('SELECT v.*, COALESCE(i.quantity,0) stock FROM product_variants v LEFT JOIN product_inventory i ON i.variant_id = v.id WHERE v.product_id = ? ORDER BY v.sort_order, v.id', [$id]) : [];
$vv = [];
if ($variants) foreach (db_all('SELECT pvv.variant_id, av.id, av.attribute_id FROM product_variant_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id WHERE pvv.variant_id IN (' . db_in(array_column($variants, 'id')) . ')', array_column($variants, 'id')) as $r) {
    $vv[(int)$r['variant_id']][(int)$r['attribute_id']] = (int)$r['id'];
}
$facetSel = $id ? array_map('intval', db_col('SELECT attribute_value_id FROM product_attribute_values WHERE product_id = ?', [$id])) : [];
$extraCats = $id ? array_map('intval', db_col('SELECT category_id FROM product_categories WHERE product_id = ?', [$id])) : [];
$rel = ['related' => [], 'cross_sell' => [], 'upsell' => []];
if ($id) foreach (db_all('SELECT related_id, relation_type FROM product_relations WHERE product_id = ? ORDER BY sort_order', [$id]) as $r) $rel[$r['relation_type']][] = (int)$r['related_id'];
$allProducts = [];
foreach (db_all('SELECT id, name, sku FROM products WHERE id <> ? ORDER BY name', [$id]) as $r) $allProducts[$r['id']] = $r['name'] . ' (' . $r['sku'] . ')';
$catOpts = categories_options(false);
$guides = [];
foreach (db_all('SELECT id, name FROM size_guides ORDER BY name') as $g) $guides[$g['id']] = $g['name'];
$sizeOpts = ['' => '—'] + array_column($sizeVals, 'value', 'id');
$colorOpts = ['' => '—'] + array_column($colorVals, 'value', 'id');

$admin_title = $id ? 'Edit: ' . $v('name') : 'Add product';
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" novalidate>
<?= csrf_field() ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <a href="<?= e(admin_url('products')) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Products</a>
  <?php if ($id): ?><a href="<?= e(url('product/' . $v('slug'))) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> <?= $v('status') === 'published' ? 'View' : 'Preview' ?></a><?php endif; ?>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <select name="status" class="form-select form-select-sm" style="width:150px"><?php foreach (['draft' => 'Draft', 'published' => 'Published', 'inactive' => 'Inactive'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-primary px-4">Save product</button>
  </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <?php foreach (['general' => 'General', 'pricing' => 'Pricing & variants', 'images' => 'Images & video', 'details' => 'Craft & details', 'custom' => 'Made to order', 'seo' => 'SEO', 'relations' => 'Related products'] as $k => $l): ?>
    <li class="nav-item"><button type="button" class="nav-link<?= $k === 'general' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#t-<?= $k ?>"><?= $l ?></button></li>
  <?php endforeach; ?>
</ul>
<div class="tab-content">
  <div class="tab-pane active" id="t-general"><div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body">
      <?= f_text('name', 'Product name', $v('name'), ['required' => true, 'maxlength' => 190]) ?>
      <div class="row"><div class="col-md-6"><?= f_text('slug', 'URL slug', $v('slug'), ['help' => '/product/your-slug — changing it creates an automatic redirect.']) ?></div>
      <div class="col-md-6"><?= f_text('sku', 'SKU', $v('sku'), ['required' => true, 'maxlength' => 80]) ?></div></div>
      <?= f_text('short_description', 'Short description', $v('short_description'), ['type' => 'textarea', 'rows' => 2, 'maxlength' => 600]) ?>
      <?= f_text('description', 'Full description (basic HTML allowed: p, h2–h4, strong, em, ul, li, a, table)', $v('description'), ['type' => 'textarea', 'rows' => 9, 'rich' => true]) ?>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
      <?= f_select('category_id', 'Category', $catOpts, $v('category_id'), ['empty' => '— None —']) ?>
      <?= f_select('subcategory_id', 'Subcategory', $catOpts, $v('subcategory_id'), ['empty' => '— None —', 'help' => 'Must sit under the selected category.']) ?>
      <?= f_select('extra_categories', 'Also show in categories', $catOpts, $extraCats, ['multiple' => true, 'size' => 7, 'help' => 'Ctrl/Cmd-click to select several.']) ?>
      <hr>
      <?php foreach (['is_featured' => 'Featured', 'is_new_arrival' => 'New arrival', 'is_best_seller' => 'Best seller (badge)', 'is_popular' => 'Popular design (badge)', 'is_limited' => 'Limited edition (badge)', 'is_handcrafted' => 'Handcrafted badge'] as $k => $l): ?>
        <?= f_toggle($k, $l, $v($k), ['wrap' => 'mb-1']) ?>
      <?php endforeach; ?>
    </div></div></div>
  </div></div>

  <div class="tab-pane" id="t-pricing">
    <div class="card mb-3"><div class="card-body"><div class="row">
      <div class="col-md-3"><?= f_text('regular_price', 'Regular price (' . currency_symbol() . ')', $v('regular_price'), ['type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true]) ?></div>
      <div class="col-md-3"><?= f_text('sale_price', 'Sale price (optional)', $v('sale_price'), ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
      <?php if ($canCost): ?><div class="col-md-3"><?= f_text('cost_price', 'Cost price (private)', $v('cost_price'), ['type' => 'number', 'step' => '0.01', 'min' => 0, 'help' => 'Visible only to roles with cost permission.']) ?></div><?php endif; ?>
      <div class="col-md-3"><?= f_text('low_stock_threshold', 'Low-stock alert at', $v('low_stock_threshold', 3), ['type' => 'number', 'min' => 0]) ?></div>
      <div class="col-md-6"><?= f_toggle('track_inventory', 'Track stock for this product', $v('track_inventory'), ['help' => 'Turn off for made-to-order pieces that are produced on demand.']) ?></div>
    </div></div></div>

    <div class="card"><div class="card-header d-flex align-items-center gap-2">Variants (size × colour) — stock is tracked per variant
      <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#genBox">Generate variants</button></div>
      <div class="collapse border-bottom" id="genBox"><div class="card-body bg-light">
        <div class="row">
          <div class="col-md-5"><label class="form-label">Sizes</label><div><?php foreach ($sizeVals as $s): ?><label class="me-2 small"><input type="checkbox" class="gen-size" value="<?= (int)$s['id'] ?>" data-label="<?= e($s['value']) ?>"> <?= e($s['value']) ?></label><?php endforeach; ?></div></div>
          <div class="col-md-5"><label class="form-label">Colours</label><div><?php foreach ($colorVals as $c): ?><label class="me-2 small"><input type="checkbox" class="gen-color" value="<?= (int)$c['id'] ?>" data-label="<?= e($c['value']) ?>"> <?= e($c['value']) ?></label><?php endforeach; ?></div></div>
          <div class="col-md-2"><label class="form-label">Stock each</label><input type="number" class="form-control form-control-sm" id="genStock" value="0" min="0"></div>
        </div>
        <button type="button" class="btn btn-sm btn-primary mt-2" id="genBtn">Add combinations</button>
        <span class="small text-muted ms-2">Add more sizes/colours under <a href="<?= e(admin_url('attributes')) ?>">Sizes, colours & attributes</a>.</span>
      </div></div>
      <div class="table-responsive"><table class="table mb-0 variant-table">
        <thead><tr><th>SKU</th><th>Size</th><th>Colour</th><th>Price override</th><th>Sale override</th><th>Stock</th><th>Status</th><th>Remove</th></tr></thead>
        <tbody id="variantRows">
          <?php foreach ($variants as $i => $var): ?>
            <tr>
              <td><input type="hidden" name="v[<?= $i ?>][id]" value="<?= (int)$var['id'] ?>"><input class="form-control form-control-sm" name="v[<?= $i ?>][sku]" value="<?= e($var['sku']) ?>"></td>
              <td><select class="form-select form-select-sm" name="v[<?= $i ?>][size]"><?php foreach ($sizeOpts as $k => $l): ?><option value="<?= e($k) ?>"<?= ($vv[(int)$var['id']][(int)$attrByCode['size']['id']] ?? '') == $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
              <td><select class="form-select form-select-sm" name="v[<?= $i ?>][color]"><?php foreach ($colorOpts as $k => $l): ?><option value="<?= e($k) ?>"<?= ($vv[(int)$var['id']][(int)$attrByCode['color']['id']] ?? '') == $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
              <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[<?= $i ?>][price]" value="<?= e($var['price_override']) ?>" placeholder="—"></td>
              <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[<?= $i ?>][sale]" value="<?= e($var['sale_price_override']) ?>" placeholder="—"></td>
              <td><input class="form-control form-control-sm" type="number" name="v[<?= $i ?>][stock]" value="<?= (int)$var['stock'] ?>"<?= can('inventory.manage') ? '' : ' readonly' ?>></td>
              <td><select class="form-select form-select-sm" name="v[<?= $i ?>][status]"><option value="active">Active</option><option value="inactive"<?= $var['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option></select></td>
              <td class="text-center"><input type="checkbox" class="form-check-input" name="v[<?= $i ?>][delete]" value="1"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-body small text-muted">Leave price overrides blank to use the product price. A product with no size/colour options keeps a single default variant. Stock changes made here are recorded in the inventory history.
        <button type="button" class="btn btn-sm btn-link" id="addVariant">+ Add a variant row</button></div>
    </div>
  </div>

  <div class="tab-pane" id="t-images">
    <div class="card mb-3"><div class="card-body">
      <label class="form-label">Upload images (front, back, side and close-up detail shots work best)</label>
      <input type="file" class="form-control" name="new_images[]" multiple accept="image/jpeg,image/png,image/webp">
      <div class="form-text">JPG, PNG or WebP up to 8 MB each. Images are re-encoded and resized to a maximum of 2400px.</div>
    </div></div>
    <div class="gallery-grid">
      <?php foreach ($images as $img): ?>
        <div class="gallery-item<?= $img['is_main'] ? ' is-main' : '' ?>">
          <img src="<?= e(img_url($img['path'])) ?>" alt="">
          <label class="small mt-2 d-block"><input type="radio" name="main_image" value="<?= (int)$img['id'] ?>"<?= $img['is_main'] ? ' checked' : '' ?>> Main image</label>
          <input class="form-control form-control-sm mt-1" name="img[<?= (int)$img['id'] ?>][alt]" value="<?= e($img['alt_text']) ?>" placeholder="Alt text (for SEO & accessibility)">
          <input class="form-control form-control-sm mt-1" name="img[<?= (int)$img['id'] ?>][caption]" value="<?= e($img['caption']) ?>" placeholder="Caption">
          <select class="form-select form-select-sm mt-1" name="img[<?= (int)$img['id'] ?>][view]"><?php foreach (['front', 'back', 'side', 'detail', 'lifestyle', 'other'] as $vt): ?><option<?= $img['view_type'] === $vt ? ' selected' : '' ?>><?= $vt ?></option><?php endforeach; ?></select>
          <select class="form-select form-select-sm mt-1" name="img[<?= (int)$img['id'] ?>][color]"><option value="">Any colour</option><?php foreach ($colorVals as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$img['color_value_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['value']) ?></option><?php endforeach; ?></select>
          <div class="d-flex gap-2 mt-1 align-items-center"><input class="form-control form-control-sm" type="number" name="img[<?= (int)$img['id'] ?>][sort]" value="<?= (int)$img['sort_order'] ?>" title="Sort order" style="width:70px">
          <label class="small text-danger"><input type="checkbox" name="img[<?= (int)$img['id'] ?>][delete]" value="1"> Delete</label></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$images): ?><p class="text-muted">No images yet.</p><?php endif; ?>
    <div class="card mt-3"><div class="card-body"><?= f_text('video_url', 'Product video (MP4 URL, optional)', $v('video_url'), ['help' => 'Upload the MP4 via cPanel File Manager (e.g. /uploads/content/video.mp4) and paste its path.']) ?></div></div>
  </div>

  <div class="tab-pane" id="t-details"><div class="card"><div class="card-body"><div class="row">
    <?php foreach (['fabric' => 'Fabric', 'embroidery_type' => 'Embroidery type', 'crochet_details' => 'Crochet flower details', 'sleeve_design' => 'Sleeve design', 'neckline_design' => 'Neckline design',
                    'abaya_length' => 'Abaya length', 'fit_silhouette' => 'Fit & silhouette', 'lining_info' => 'Lining', 'transparency_info' => 'Transparency', 'model_info' => 'Model measurements / size worn',
                    'dispatch_time' => 'Dispatch time text', 'dimensions' => 'Dimensions (packaged)'] as $k => $l): ?>
      <div class="col-md-6"><?= f_text($k, $l, $v($k)) ?></div>
    <?php endforeach; ?>
    <div class="col-md-6"><?= f_text('weight_grams', 'Weight (grams)', $v('weight_grams'), ['type' => 'number', 'min' => 0]) ?></div>
    <div class="col-md-6"><?= f_select('size_guide_id', 'Size guide', $guides, $v('size_guide_id'), ['empty' => 'Default (Size Guide page)']) ?></div>
    <div class="col-md-6"><?= f_text('material_details', 'Fabric & material notes', $v('material_details'), ['type' => 'textarea', 'rows' => 4]) ?></div>
    <div class="col-md-6"><?= f_text('care_instructions', 'Care instructions', $v('care_instructions'), ['type' => 'textarea', 'rows' => 4]) ?></div>
    <?php foreach ($attrs as $a): if ($a['is_variant']) continue; ?>
      <div class="col-md-6 mb-3"><label class="form-label"><?= e($a['name']) ?> (used in filters)</label><div>
        <?php foreach ($attrVals[(int)$a['id']] ?? [] as $av): ?><label class="me-3 small"><input type="checkbox" name="facets[]" value="<?= (int)$av['id'] ?>"<?= in_array((int)$av['id'], $facetSel, true) ? ' checked' : '' ?>> <?= e($av['value']) ?></label><?php endforeach; ?>
      </div></div>
    <?php endforeach; ?>
  </div></div></div></div>

  <div class="tab-pane" id="t-custom"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= f_select('fulfillment_type', 'Fulfilment', ['ready_to_ship' => 'Ready to ship', 'made_to_order' => 'Made to order'], $v('fulfillment_type')) ?></div>
    <div class="col-md-4"><?= f_text('production_lead_days', 'Production lead time (days)', $v('production_lead_days', 0), ['type' => 'number', 'min' => 0, 'help' => 'Added to delivery estimates for made-to-order or customised items.']) ?></div>
    <div class="col-md-4"><?= f_text('customization_fee', 'Customisation charge per piece', $v('customization_fee', 0), ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div>
    <div class="col-12"><?= f_toggle('allow_customization', 'Offer customisation on this product', $v('allow_customization'), ['help' => 'Fields below appear on the product page only when this is on.']) ?></div>
    <div class="col-md-4"><?= f_toggle('custom_length_enabled', 'Custom length', $v('custom_length_enabled')) ?></div>
    <div class="col-md-4"><?= f_text('custom_length_min', 'Min length (in)', $v('custom_length_min'), ['type' => 'number']) ?></div>
    <div class="col-md-4"><?= f_text('custom_length_max', 'Max length (in)', $v('custom_length_max'), ['type' => 'number']) ?></div>
    <div class="col-md-4"><?= f_toggle('custom_sleeve_enabled', 'Sleeve / design preference', $v('custom_sleeve_enabled')) ?></div>
    <div class="col-md-8"><?= f_text('custom_sleeve_options', 'Sleeve options (comma separated)', $v('custom_sleeve_options'), ['placeholder' => 'Standard, Fuller bell, Cuffed']) ?></div>
    <div class="col-md-4"><?= f_toggle('custom_notes_enabled', 'Additional instructions box', $v('custom_notes_enabled')) ?></div>
    <div class="col-md-8"><?= f_text('custom_terms', 'Custom-order note shown to customer (optional)', $v('custom_terms'), ['help' => 'Defaults to the store-wide text in Settings → Checkout.']) ?></div>
  </div></div></div></div>

  <div class="tab-pane" id="t-seo"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-6"><?= f_text('seo_title', 'SEO title', $v('seo_title'), ['maxlength' => 190, 'help' => 'Ideally under 60 characters.']) ?></div>
    <div class="col-md-6"><?= f_text('canonical_url', 'Canonical URL (optional)', $v('canonical_url'), ['help' => 'Leave blank to use the product URL.']) ?></div>
    <div class="col-12"><?= f_text('meta_description', 'Meta description', $v('meta_description'), ['type' => 'textarea', 'rows' => 2, 'maxlength' => 320, 'help' => 'Ideally 140–160 characters.']) ?></div>
    <div class="col-md-6"><?= f_text('og_title', 'Open Graph title', $v('og_title')) ?></div>
    <div class="col-md-6"><?= f_text('og_description', 'Open Graph description', $v('og_description')) ?></div>
    <div class="col-md-6"><?= f_image('og_image', 'Open Graph image (defaults to the main image)', $v('og_image') ?: null, ['field' => 'og_image_file', 'remove' => 'remove_og_image']) ?></div>
    <div class="col-md-6"><?= f_toggle('noindex', 'Hide from search engines (noindex)', $v('noindex')) ?></div>
  </div></div></div></div>

  <div class="tab-pane" id="t-relations"><div class="card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= f_select('rel_related', 'Related products', $allProducts, $rel['related'], ['multiple' => true, 'size' => 12, 'help' => 'Falls back to same-category products.']) ?></div>
    <div class="col-md-4"><?= f_select('rel_cross_sell', 'Cross-sells (“Complete the look”)', $allProducts, $rel['cross_sell'], ['multiple' => true, 'size' => 12]) ?></div>
    <div class="col-md-4"><?= f_select('rel_upsell', 'Upsells (“You may also like”)', $allProducts, $rel['upsell'], ['multiple' => true, 'size' => 12]) ?></div>
  </div></div></div></div>
</div>
<div class="mt-3 text-end"><button class="btn btn-primary px-4">Save product</button></div>
</form>
<template id="variantTpl">
  <tr>
    <td><input type="hidden" name="v[__i__][id]" value="0"><input class="form-control form-control-sm" name="v[__i__][sku]" value="__sku__"></td>
    <td><select class="form-select form-select-sm" name="v[__i__][size]"><?php foreach ($sizeOpts as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
    <td><select class="form-select form-select-sm" name="v[__i__][color]"><?php foreach ($colorOpts as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
    <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[__i__][price]" placeholder="—"></td>
    <td><input class="form-control form-control-sm" type="number" step="0.01" name="v[__i__][sale]" placeholder="—"></td>
    <td><input class="form-control form-control-sm" type="number" name="v[__i__][stock]" value="0"></td>
    <td><select class="form-select form-select-sm" name="v[__i__][status]"><option value="active">Active</option><option value="inactive">Inactive</option></select></td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove()">×</button></td>
  </tr>
</template>
<?php $adminScripts = <<<'JS'
<script>
(function () {
  var idx = 1000;
  var tpl = document.getElementById('variantTpl').innerHTML;
  var skuBase = function () { return (document.getElementById('f_sku').value || 'SKU').toUpperCase(); };
  var addRow = function (size, color, sLabel, cLabel, stock) {
    var i = idx++;
    var sku = skuBase() + (cLabel ? '-' + cLabel.substr(0, 3).toUpperCase().replace(/[^A-Z0-9]/g, '') : '') + (sLabel ? '-' + sLabel.replace(/[^A-Za-z0-9]/g, '') : '') || skuBase() + '-' + i;
    var tr = document.createElement('tbody');
    tr.innerHTML = tpl.replace(/__i__/g, i).replace('__sku__', sku);
    var row = tr.firstElementChild;
    if (size) row.querySelector('[name$="[size]"]').value = size;
    if (color) row.querySelector('[name$="[color]"]').value = color;
    if (stock) row.querySelector('[name$="[stock]"]').value = stock;
    document.getElementById('variantRows').appendChild(row);
  };
  document.getElementById('addVariant').addEventListener('click', function () { addRow(); });
  document.getElementById('genBtn').addEventListener('click', function () {
    var sizes = [].slice.call(document.querySelectorAll('.gen-size:checked'));
    var colors = [].slice.call(document.querySelectorAll('.gen-color:checked'));
    if (!sizes.length) sizes = [null];
    if (!colors.length) colors = [null];
    var stock = document.getElementById('genStock').value;
    colors.forEach(function (c) { sizes.forEach(function (s) {
      addRow(s && s.value, c && c.value, s && s.dataset.label, c && c.dataset.label, stock);
    }); });
  });
})();
</script>
JS;
require __DIR__ . '/partials/footer.php';
