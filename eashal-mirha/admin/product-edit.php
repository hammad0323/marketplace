<?php
require __DIR__ . '/includes/admin.php';

$id = (int)get('id');
$p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('error', 'Product not found.'); redirect('admin/products'); }

$defaults = [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => '', 'subcategory_id' => '', 'short_description' => '', 'description' => '',
    'price' => '', 'sale_price' => '', 'stock' => 10, 'sizes' => 'XS,S,M,L,XL', 'colors' => '', 'fabric' => '', 'pieces' => '',
    'is_new' => 1, 'is_bestseller' => 0, 'is_featured' => 0, 'status' => 1,
    'meta_title' => '', 'meta_description' => '', 'meta_keywords' => '', 'focus_keyword' => '', 'canonical_url' => '', 'og_image' => '', 'noindex' => 0,
];
$d = $p ?: $defaults;
$errors = [];

if (is_post()) {
    require_csrf();
    foreach ($defaults as $k => $v) {
        if ($k !== 'og_image') $d[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $v;
    }
    foreach (['is_new', 'is_bestseller', 'is_featured', 'status', 'noindex'] as $k) $d[$k] = post($k) === '1' ? 1 : 0;
    if ($d['name'] === '') $errors[] = 'Product name is required.';
    if (!is_numeric($d['price']) || (float)$d['price'] < 0) $errors[] = 'Enter a valid price.';
    if ($d['sale_price'] !== '' && (!is_numeric($d['sale_price']) || (float)$d['sale_price'] >= (float)$d['price'])) $errors[] = 'Sale price must be lower than the regular price (or leave it empty).';
    $d['sizes'] = implode(',', array_filter(array_map('trim', explode(',', $d['sizes'])), 'strlen'));
    $d['colors'] = implode(',', array_filter(array_map('trim', explode(',', $d['colors'])), 'strlen'));
    $d['slug'] = unique_slug('products', $d['slug'] !== '' ? $d['slug'] : $d['name'], $id);
    $d['og_image'] = handle_image('og_image', $p['og_image'] ?? null, 'products');
    if ($d['sku'] !== '' && val('SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $id])) $errors[] = 'Another product already uses this SKU.';

    if (!$errors) {
        $vals = [
            $d['category_id'] ?: null, $d['subcategory_id'] ?: null, $d['name'], $d['slug'], $d['sku'] ?: null, $d['short_description'], $d['description'],
            (float)$d['price'], $d['sale_price'] === '' ? null : (float)$d['sale_price'], (int)$d['stock'], $d['sizes'], $d['colors'], $d['fabric'], $d['pieces'],
            $d['is_new'], $d['is_bestseller'], $d['is_featured'], $d['status'],
            $d['meta_title'], $d['meta_description'], $d['meta_keywords'], $d['focus_keyword'], $d['canonical_url'], $d['og_image'], $d['noindex'],
        ];
        $cols = 'category_id=?, subcategory_id=?, name=?, slug=?, sku=?, short_description=?, description=?, price=?, sale_price=?, stock=?, sizes=?, colors=?, fabric=?, pieces=?, is_new=?, is_bestseller=?, is_featured=?, status=?, meta_title=?, meta_description=?, meta_keywords=?, focus_keyword=?, canonical_url=?, og_image=?, noindex=?';
        if ($id) {
            q("UPDATE products SET $cols, updated_at = NOW() WHERE id = ?", array_merge($vals, [$id]));
        } else {
            q("INSERT INTO products SET $cols", $vals);
            $id = (int)db()->lastInsertId();
        }
        // Images: new uploads
        $next = (int)val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?', [$id]);
        foreach (files_list('images') as $f) {
            if ($path = upload_image($f, 'products')) q('INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)', [$id, $path, $next++]);
        }
        // Images: order + delete
        foreach ((array)($_POST['img_order'] ?? []) as $imgId => $ord) q('UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?', [(int)$ord, (int)$imgId, $id]);
        foreach ((array)($_POST['img_delete'] ?? []) as $imgId) {
            $im = row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int)$imgId, $id]);
            if ($im) {
                if (!val('SELECT COUNT(*) FROM product_images WHERE image = ? AND id <> ?', [$im['image'], $im['id']])) delete_upload($im['image']);
                q('DELETE FROM product_images WHERE id = ?', [$im['id']]);
            }
        }
        flash('success', 'Product saved.');
        redirect(post('save_new') === '1' ? 'admin/product-edit' : 'admin/product-edit?id=' . $id);
    }
}

$cats = category_tree();
$images = $id ? product_images($id) : [];
admin_header($id ? 'Edit Product' : 'Add Product', 'products');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="edit-layout">
  <?= csrf_field() ?>
  <div class="edit-main">
    <div class="card">
      <div class="card__head"><h3>General</h3><?php if ($id): ?><a class="link" target="_blank" href="<?= url('product/' . $d['slug']) ?>">View on store ↗</a><?php endif; ?></div>
      <?= f_text('name', 'Product name *', $d['name'], ['attrs' => 'required data-slug-source']) ?>
      <?= f_text('slug', 'URL slug', $d['slug'], ['attrs' => 'data-slug-target', 'help' => 'Your link: ' . e(site_url()) . '/product/<b data-slug-preview>' . e($d['slug']) . '</b> — leave empty to generate from the name.']) ?>
      <?= f_text('short_description', 'Short description', $d['short_description'], ['type' => 'textarea', 'rows' => 2]) ?>
      <label class="field"><span>Full description</span><textarea name="description" rows="10" data-editor><?= e($d['description']) ?></textarea></label>
    </div>

    <div class="card">
      <div class="card__head"><h3>Images</h3><span class="muted sm">First image is the main image · second shows on hover</span></div>
      <div class="img-grid" data-sortable>
        <?php foreach ($images as $i => $im): ?>
          <div class="img-item" draggable="true">
            <img src="<?= e(img($im['image'])) ?>" alt="">
            <input type="hidden" name="img_order[<?= $im['id'] ?>]" value="<?= $i ?>" data-order>
            <label class="img-del"><input type="checkbox" name="img_delete[]" value="<?= $im['id'] ?>"> Delete</label>
            <?php if ($i === 0): ?><span class="img-main">Main</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <label class="dropzone"><input type="file" name="images[]" multiple accept="image/*" data-multi-preview><span><?= aicon('image') ?> Click or drop images here (JPG, PNG, WEBP · up to 8 MB each)</span></label>
      <div class="img-grid" data-multi-out></div>
    </div>

    <div class="card">
      <div class="card__head"><h3>Pricing & Inventory</h3></div>
      <div class="row-3">
        <?= f_text('price', 'Regular price (' . e(setting('currency', 'Rs.')) . ') *', $d['price'], ['type' => 'number', 'attrs' => 'step="0.01" min="0" required']) ?>
        <?= f_text('sale_price', 'Sale price', $d['sale_price'], ['type' => 'number', 'attrs' => 'step="0.01" min="0"', 'help' => 'Leave empty for no discount']) ?>
        <?= f_text('stock', 'Stock quantity', $d['stock'], ['type' => 'number', 'attrs' => 'min="0"']) ?>
      </div>
      <div class="row-3">
        <?= f_text('sku', 'SKU', $d['sku']) ?>
        <?= f_text('fabric', 'Fabric', $d['fabric'], ['attrs' => 'list="fabrics"']) ?>
        <?= f_text('pieces', 'Pieces', $d['pieces'], ['attrs' => 'list="pieces"']) ?>
      </div>
      <datalist id="fabrics"><?php foreach (['Lawn', 'Cambric', 'Khaddar', 'Chiffon', 'Organza', 'Silk', 'Raw Silk', 'Net', 'Velvet', 'Jamawar', 'Cotton', 'Linen', 'Tissue'] as $f): ?><option value="<?= $f ?>"><?php endforeach; ?></datalist>
      <datalist id="pieces"><?php foreach (['1 Piece', '2 Piece', '3 Piece', '4 Piece'] as $f): ?><option value="<?= $f ?>"><?php endforeach; ?></datalist>
      <div class="row-2">
        <?= f_text('sizes', 'Sizes (comma separated)', $d['sizes'], ['help' => 'e.g. XS,S,M,L,XL — or "Unstitched" / "Custom". Leave empty for no size option.']) ?>
        <?= f_text('colors', 'Colours (comma separated)', $d['colors'], ['help' => 'e.g. Black,Gold — optional']) ?>
      </div>
      <div class="quick-sizes">Quick fill:
        <button type="button" class="chip" data-fill="sizes" data-v="XS,S,M,L,XL">XS–XL</button>
        <button type="button" class="chip" data-fill="sizes" data-v="S,M,L">S–L</button>
        <button type="button" class="chip" data-fill="sizes" data-v="Unstitched">Unstitched</button>
        <button type="button" class="chip" data-fill="sizes" data-v="Custom">Custom</button>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h3>Search Engine Optimisation</h3><span class="muted sm">Leave empty to use automatic values</span></div>
      <div class="serp">
        <span class="serp__url"><?= e(site_url()) ?>/product/<b data-slug-preview><?= e($d['slug'] ?: 'your-product') ?></b></span>
        <span class="serp__title" data-serp-title><?= e($d['meta_title'] ?: ($d['name'] ?: 'Product title') . ' | ' . setting('site_name')) ?></span>
        <span class="serp__desc" data-serp-desc><?= e($d['meta_description'] ?: excerpt($d['short_description'] ?: $d['description'], 158) ?: 'Your meta description will appear here.') ?></span>
      </div>
      <?= f_text('meta_title', 'Meta title', $d['meta_title'], ['attrs' => 'data-count="60" data-serp="title"', 'help' => 'Best under 60 characters.']) ?>
      <?= f_text('meta_description', 'Meta description', $d['meta_description'], ['type' => 'textarea', 'rows' => 3, 'attrs' => 'data-count="160" data-serp="desc"', 'help' => 'Best between 120 and 160 characters.']) ?>
      <div class="row-2">
        <?= f_text('focus_keyword', 'Focus keyword', $d['focus_keyword']) ?>
        <?= f_text('meta_keywords', 'Meta keywords', $d['meta_keywords'], ['help' => 'Comma separated']) ?>
      </div>
      <?= f_text('canonical_url', 'Canonical URL', $d['canonical_url'], ['type' => 'url', 'help' => 'Only if this product duplicates another page.']) ?>
      <?= f_image('og_image', 'Social share image (Open Graph)', $d['og_image'], 'Defaults to the main product image. 1200×630 recommended.') ?>
      <?= f_switch('noindex', 'Hide from search engines (noindex)', $d['noindex']) ?>
    </div>
  </div>

  <aside class="edit-side">
    <div class="card sticky">
      <div class="card__head"><h3>Publish</h3></div>
      <?= f_switch('status', 'Published (visible on store)', $d['status']) ?>
      <?= f_switch('is_new', 'New Arrival', $d['is_new'], 'Shows in New Arrivals carousel') ?>
      <?= f_switch('is_bestseller', 'Best Seller', $d['is_bestseller'], 'Shows in Best Sellers carousel') ?>
      <?= f_switch('is_featured', 'Featured', $d['is_featured'], 'Shown first in collection tabs') ?>
      <button class="btn btn-primary btn-block" type="submit">Save Product</button>
      <button class="btn btn-block" type="submit" name="save_new" value="1">Save & Add Another</button>
      <?php if ($id): ?><p class="muted sm">Views: <?= (int)$p['views'] ?> · Sold: <?= (int)$p['sales_count'] ?></p><?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h3>Category</h3></div>
      <label class="field"><span>Category</span>
        <select name="category_id" data-parent-select>
          <option value="">— None —</option>
          <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $d['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Sub-category</span>
        <select name="subcategory_id" data-child-select>
          <option value="">— None —</option>
          <?php foreach ($cats as $c): foreach ($c['children'] as $s): ?><option value="<?= $s['id'] ?>" data-parent="<?= $c['id'] ?>" <?= $d['subcategory_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; endforeach; ?>
        </select>
      </label>
      <a class="link sm" href="<?= url('admin/categories') ?>">Manage categories →</a>
    </div>
  </aside>
</form>
<?php admin_footer();
