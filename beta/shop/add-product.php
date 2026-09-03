<?php
require __DIR__ . '/../config/config.php';
require_permission('add_product');
$shopId = active_shop_id();
$categories = db_fetch_all("SELECT * FROM categories WHERE status='active' ORDER BY name");
$brands = db_fetch_all("SELECT * FROM brands WHERE status='active' ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
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
    $defaultStatus = get_setting('default_product_status', 'pending');

    if (!$name || !$categoryId || $regularPrice <= 0) {
        flash('error', 'Please fill in all required fields.');
        redirect(shop_url('add-product.php'));
    }

    $mainImageUpload = handle_image_upload('main_image', 'products');
    if (isset($mainImageUpload['error'])) { flash('error', $mainImageUpload['error']); redirect(shop_url('add-product.php')); }

    $slug = unique_slug('products', slugify($name));
    $productId = db_insert("INSERT INTO products (shop_id,category_id,subcategory_id,brand_id,name,slug,sku,short_description,description,
        product_type,regular_price,sale_price,cost_price,tax_percent,stock_quantity,low_stock_threshold,manage_stock,stock_status,main_image,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        'iiiissssssdddiiiisss', [$shopId, $categoryId, $subcategoryId, $brandId, $name, $slug, $sku, $shortDesc, $description,
            $productType, $regularPrice, $salePrice, $costPrice, $taxPercent, $stockQty, $lowStock, $manageStock, $stockStatus, $mainImageUpload['path'] ?? null, $defaultStatus]);

    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmp) {
            if (!$tmp) continue;
            $_FILES['gallery_tmp'] = ['name' => $_FILES['gallery_images']['name'][$i], 'type' => $_FILES['gallery_images']['type'][$i],
                'tmp_name' => $tmp, 'error' => $_FILES['gallery_images']['error'][$i], 'size' => $_FILES['gallery_images']['size'][$i]];
            $upload = handle_image_upload('gallery_tmp', 'products');
            if (!empty($upload['path'])) db_insert("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)", 'isi', [$productId, $upload['path'], $i]);
        }
    }

    if ($productType === 'variable' && !empty($_POST['variation_label'])) {
        foreach ($_POST['variation_label'] as $i => $label) {
            if (!trim($label)) continue;
            db_insert("INSERT INTO product_variations (product_id, variation_label, sku, price, stock_quantity) VALUES (?,?,?,?,?)",
                'issdi', [$productId, trim($label), trim($_POST['variation_sku'][$i] ?? ''), (float)$_POST['variation_price'][$i], (int)$_POST['variation_stock'][$i]]);
        }
    }

    $seoScore = calculate_seo_score(['meta_title' => $_POST['meta_title'] ?? $name, 'meta_description' => $_POST['meta_description'] ?? $shortDesc,
        'focus_keyword' => $_POST['focus_keyword'] ?? '', 'content' => $description, 'canonical_url' => base_url('product.php?slug=' . $slug),
        'og_image' => '', 'schema_type' => 'Product']);
    save_seo_meta('product', $productId, [
        'meta_title' => trim($_POST['meta_title'] ?? $name), 'meta_description' => trim($_POST['meta_description'] ?? $shortDesc),
        'focus_keyword' => trim($_POST['focus_keyword'] ?? ''), 'canonical_url' => base_url('product.php?slug=' . $slug),
        'schema_type' => 'Product', 'seo_score' => $seoScore['score'],
    ]);

    audit_log(current_shop_owner() ? 'shop_owner' : 'shop_staff', current_shop_owner()['id'] ?? current_shop_staff()['id'],
        current_shop_owner()['name'] ?? current_shop_staff()['name'], 'Added product', 'products', $productId, $name);
    flash('success', "Product added successfully! SEO Score: {$seoScore['score']}/100. " . ($defaultStatus === 'pending' ? 'Pending admin approval.' : ''));
    redirect(shop_url('products.php'));
}

$dashRole = current_shop_owner() ? 'shop' : 'employee'; $pageTitle = 'Add Product';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Add Product</h1></div>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="dash-form-card">
    <h3>Basic Information</h3>
    <div class="form-row">
      <div><label>Product Name *</label><input type="text" name="name" required></div>
      <div><label>SKU</label><input type="text" name="sku"></div>
    </div>
    <div class="form-row">
      <div><label>Category *</label>
        <select name="category_id" id="category-select" required onchange="loadSubcategories()">
          <option value="">Select Category</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Subcategory</label><select name="subcategory_id" id="subcategory-select"><option value="">Select Subcategory</option></select></div>
      <div><label>Brand</label>
        <select name="brand_id"><option value="">None</option><?php foreach ($brands as $b): ?><option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <label>Short Description</label><input type="text" name="short_description" maxlength="500">
    <label>Full Description</label><textarea name="description" rows="6" class="rich-editor"></textarea>
  </div>

  <div class="dash-form-card">
    <h3>Pricing</h3>
    <div class="form-row">
      <div><label>Regular Price *</label><input type="number" step="0.01" name="regular_price" required></div>
      <div><label>Sale Price</label><input type="number" step="0.01" name="sale_price"></div>
      <div><label>Cost Price</label><input type="number" step="0.01" name="cost_price"></div>
      <div><label>Tax %</label><input type="number" step="0.01" name="tax_percent" value="0"></div>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Inventory</h3>
    <div class="form-row">
      <div><label>Stock Quantity</label><input type="number" name="stock_quantity" value="0"></div>
      <div><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" value="5"></div>
      <div><label class="switch-label"><input type="checkbox" name="manage_stock" checked> Manage Stock</label></div>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Product Type</h3>
    <select name="product_type" id="product-type-select" onchange="document.getElementById('variations-block').style.display = this.value==='variable'?'block':'none'">
      <option value="simple">Simple Product</option><option value="variable">Variable Product</option>
      <option value="digital">Digital Product</option><option value="physical">Physical Product</option>
    </select>
  </div>

  <div class="dash-form-card" id="variations-block" style="display:none">
    <h3>Variations</h3>
    <div id="variation-rows">
      <div class="form-row variation-row">
        <input type="text" name="variation_label[]" placeholder="e.g. Red / Large">
        <input type="text" name="variation_sku[]" placeholder="SKU">
        <input type="number" step="0.01" name="variation_price[]" placeholder="Price">
        <input type="number" name="variation_stock[]" placeholder="Stock">
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline" onclick="addVariationRow()">+ Add Variation</button>
  </div>

  <div class="dash-form-card">
    <h3>Images</h3>
    <label>Main Image</label><input type="file" name="main_image" accept=".jpg,.jpeg,.png,.webp">
    <label>Gallery Images (multiple)</label><input type="file" name="gallery_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
  </div>

  <div class="dash-form-card">
    <h3>SEO</h3>
    <label>Meta Title</label><input type="text" name="meta_title">
    <label>Meta Description</label><textarea name="meta_description" rows="2"></textarea>
    <label>Focus Keyword</label><input type="text" name="focus_keyword">
  </div>

  <button type="submit" class="btn btn-primary">Save Product</button>
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
