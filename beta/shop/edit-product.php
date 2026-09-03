<?php
require __DIR__ . '/../config/config.php';
require_permission('edit_product');
$shopId = active_shop_id();
$id = (int)($_GET['id'] ?? 0);
$product = db_fetch_one("SELECT * FROM products WHERE id=? AND shop_id=?", 'ii', [$id, $shopId]);
if (!$product) { flash('error', 'Product not found.'); redirect(shop_url('products.php')); }

$categories = db_fetch_all("SELECT * FROM categories WHERE status='active' ORDER BY name");
$brands = db_fetch_all("SELECT * FROM brands WHERE status='active' ORDER BY name");
$subcategories = db_fetch_all("SELECT * FROM subcategories WHERE category_id=?", 'i', [$product['category_id']]);
$galleryImages = db_fetch_all("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order", 'i', [$id]);
$variations = db_fetch_all("SELECT * FROM product_variations WHERE product_id=?", 'i', [$id]);
$seo = get_seo_meta('product', $id) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['delete_image_id'])) {
        db_exec("DELETE FROM product_images WHERE id=? AND product_id=?", 'ii', [(int)$_POST['delete_image_id'], $id]);
        redirect(shop_url('edit-product.php?id=' . $id));
    }

    $name = trim($_POST['name']); $sku = trim($_POST['sku']);
    $categoryId = (int)$_POST['category_id']; $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $shortDesc = trim($_POST['short_description']); $description = clean_html($_POST['description'] ?? '');
    $productType = $_POST['product_type']; $regularPrice = (float)$_POST['regular_price'];
    $salePrice = $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
    $costPrice = $_POST['cost_price'] !== '' ? (float)$_POST['cost_price'] : null;
    $taxPercent = (float)($_POST['tax_percent'] ?? 0);
    $stockQty = (int)$_POST['stock_quantity']; $lowStock = (int)$_POST['low_stock_threshold'];
    $manageStock = isset($_POST['manage_stock']) ? 1 : 0;
    $stockStatus = $stockQty > 0 ? 'in_stock' : 'out_of_stock';

    $mainImageUpload = handle_image_upload('main_image', 'products');
    if (isset($mainImageUpload['error'])) { flash('error', $mainImageUpload['error']); redirect(shop_url('edit-product.php?id=' . $id)); }
    $mainImagePath = $mainImageUpload['path'] ?? $product['main_image'];

    db_exec("UPDATE products SET category_id=?,subcategory_id=?,brand_id=?,name=?,sku=?,short_description=?,description=?,
        product_type=?,regular_price=?,sale_price=?,cost_price=?,tax_percent=?,stock_quantity=?,low_stock_threshold=?,manage_stock=?,stock_status=?,main_image=? WHERE id=?",
        'iiisssssdddiiiissi', [$categoryId, $subcategoryId, $brandId, $name, $sku, $shortDesc, $description, $productType,
            $regularPrice, $salePrice, $costPrice, $taxPercent, $stockQty, $lowStock, $manageStock, $stockStatus, $mainImagePath, $id]);

    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmp) {
            if (!$tmp) continue;
            $_FILES['gallery_tmp'] = ['name' => $_FILES['gallery_images']['name'][$i], 'type' => $_FILES['gallery_images']['type'][$i],
                'tmp_name' => $tmp, 'error' => $_FILES['gallery_images']['error'][$i], 'size' => $_FILES['gallery_images']['size'][$i]];
            $upload = handle_image_upload('gallery_tmp', 'products');
            if (!empty($upload['path'])) db_insert("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)", 'isi', [$id, $upload['path'], $i]);
        }
    }

    db_exec("DELETE FROM product_variations WHERE product_id=?", 'i', [$id]);
    if ($productType === 'variable' && !empty($_POST['variation_label'])) {
        foreach ($_POST['variation_label'] as $i => $label) {
            if (!trim($label)) continue;
            db_insert("INSERT INTO product_variations (product_id, variation_label, sku, price, stock_quantity) VALUES (?,?,?,?,?)",
                'issdi', [$id, trim($label), trim($_POST['variation_sku'][$i] ?? ''), (float)$_POST['variation_price'][$i], (int)$_POST['variation_stock'][$i]]);
        }
    }

    $seoScore = calculate_seo_score(['meta_title' => $_POST['meta_title'] ?? $name, 'meta_description' => $_POST['meta_description'] ?? $shortDesc,
        'focus_keyword' => $_POST['focus_keyword'] ?? '', 'content' => $description, 'canonical_url' => base_url('product.php?slug=' . $product['slug']),
        'og_image' => '', 'schema_type' => 'Product']);
    save_seo_meta('product', $id, [
        'meta_title' => trim($_POST['meta_title'] ?? $name), 'meta_description' => trim($_POST['meta_description'] ?? $shortDesc),
        'focus_keyword' => trim($_POST['focus_keyword'] ?? ''), 'seo_score' => $seoScore['score'],
    ]);

    flash('success', "Product updated. SEO Score: {$seoScore['score']}/100");
    redirect(shop_url('products.php'));
}

$dashRole = current_shop_owner() ? 'shop' : 'employee'; $pageTitle = 'Edit Product';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Edit Product</h1></div>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="dash-form-card">
    <h3>Basic Information</h3>
    <div class="form-row">
      <div><label>Product Name *</label><input type="text" name="name" value="<?= clean($product['name']) ?>" required></div>
      <div><label>SKU</label><input type="text" name="sku" value="<?= clean($product['sku']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Category *</label>
        <select name="category_id" id="category-select" required onchange="loadSubcategories()">
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id']==$product['category_id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Subcategory</label><select name="subcategory_id" id="subcategory-select">
        <option value="">Select Subcategory</option>
        <?php foreach ($subcategories as $sc): ?><option value="<?= $sc['id'] ?>" <?= $sc['id']==$product['subcategory_id']?'selected':'' ?>><?= clean($sc['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Brand</label><select name="brand_id"><option value="">None</option>
        <?php foreach ($brands as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id']==$product['brand_id']?'selected':'' ?>><?= clean($b['name']) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <label>Short Description</label><input type="text" name="short_description" value="<?= clean($product['short_description']) ?>">
    <label>Full Description</label><textarea name="description" rows="6"><?= clean($product['description']) ?></textarea>
  </div>

  <div class="dash-form-card">
    <h3>Pricing</h3>
    <div class="form-row">
      <div><label>Regular Price *</label><input type="number" step="0.01" name="regular_price" value="<?= $product['regular_price'] ?>" required></div>
      <div><label>Sale Price</label><input type="number" step="0.01" name="sale_price" value="<?= clean($product['sale_price']) ?>"></div>
      <div><label>Cost Price</label><input type="number" step="0.01" name="cost_price" value="<?= clean($product['cost_price']) ?>"></div>
      <div><label>Tax %</label><input type="number" step="0.01" name="tax_percent" value="<?= $product['tax_percent'] ?>"></div>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Inventory</h3>
    <div class="form-row">
      <div><label>Stock Quantity</label><input type="number" name="stock_quantity" value="<?= $product['stock_quantity'] ?>"></div>
      <div><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" value="<?= $product['low_stock_threshold'] ?>"></div>
      <div><label class="switch-label"><input type="checkbox" name="manage_stock" <?= $product['manage_stock'] ? 'checked' : '' ?>> Manage Stock</label></div>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Product Type</h3>
    <select name="product_type" onchange="document.getElementById('variations-block').style.display = this.value==='variable'?'block':'none'">
      <?php foreach (['simple'=>'Simple Product','variable'=>'Variable Product','digital'=>'Digital Product','physical'=>'Physical Product'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $product['product_type']===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="dash-form-card" id="variations-block" style="<?= $product['product_type']==='variable'?'':'display:none' ?>">
    <h3>Variations</h3>
    <div id="variation-rows">
      <?php $rows = $variations ?: [['variation_label'=>'','sku'=>'','price'=>'','stock_quantity'=>'']]; foreach ($rows as $v): ?>
        <div class="form-row variation-row">
          <input type="text" name="variation_label[]" placeholder="e.g. Red / Large" value="<?= clean($v['variation_label']) ?>">
          <input type="text" name="variation_sku[]" placeholder="SKU" value="<?= clean($v['sku']) ?>">
          <input type="number" step="0.01" name="variation_price[]" placeholder="Price" value="<?= clean($v['price']) ?>">
          <input type="number" name="variation_stock[]" placeholder="Stock" value="<?= clean($v['stock_quantity']) ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline" onclick="addVariationRow()">+ Add Variation</button>
  </div>

  <div class="dash-form-card">
    <h3>Images</h3>
    <label>Main Image</label>
    <img class="table-thumb" src="<?= product_image_or_default($product['main_image']) ?>"><br>
    <input type="file" name="main_image" accept=".jpg,.jpeg,.png,.webp">
    <label>Gallery Images</label>
    <div class="gallery-manage">
      <?php foreach ($galleryImages as $img): ?>
        <div class="gallery-manage-item">
          <img src="<?= upload_url($img['image_path']) ?>">
          <form method="post" onsubmit="return confirm('Remove image?')"><?= csrf_field() ?><input type="hidden" name="delete_image_id" value="<?= $img['id'] ?>"><button class="btn btn-sm btn-danger">&times;</button></form>
        </div>
      <?php endforeach; ?>
    </div>
    <input type="file" name="gallery_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
  </div>

  <div class="dash-form-card">
    <h3>SEO <?php if (!empty($seo['seo_score'])): ?><span class="badge badge-success">Score: <?= $seo['seo_score'] ?>/100</span><?php endif; ?></h3>
    <label>Meta Title</label><input type="text" name="meta_title" value="<?= clean($seo['meta_title'] ?? '') ?>">
    <label>Meta Description</label><textarea name="meta_description" rows="2"><?= clean($seo['meta_description'] ?? '') ?></textarea>
    <label>Focus Keyword</label><input type="text" name="focus_keyword" value="<?= clean($seo['focus_keyword'] ?? '') ?>">
  </div>

  <button type="submit" class="btn btn-primary">Update Product</button>
  <a href="<?= shop_url('products.php') ?>" class="btn btn-outline">Cancel</a>
</form>
<script>
const subcategoriesByCategory = <?= json_encode(array_reduce(db_fetch_all("SELECT * FROM subcategories WHERE status='active'"), function($carry, $sc) {
    $carry[$sc['category_id']][] = ['id' => $sc['id'], 'name' => $sc['name']]; return $carry; }, [])) ?>;
function loadSubcategories() {
  const catId = document.getElementById('category-select').value;
  const sel = document.getElementById('subcategory-select');
  sel.innerHTML = '<option value="">Select Subcategory</option>';
  (subcategoriesByCategory[catId] || []).forEach(sc => sel.innerHTML += `<option value="${sc.id}">${sc.name}</option>`);
}
function addVariationRow() {
  const div = document.createElement('div'); div.className = 'form-row variation-row';
  div.innerHTML = '<input type="text" name="variation_label[]" placeholder="e.g. Red / Large"><input type="text" name="variation_sku[]" placeholder="SKU"><input type="number" step="0.01" name="variation_price[]" placeholder="Price"><input type="number" name="variation_stock[]" placeholder="Stock">';
  document.getElementById('variation-rows').appendChild(div);
}
</script>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
