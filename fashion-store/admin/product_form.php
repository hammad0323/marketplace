<?php
$pageTitle = 'Add / Edit Product';
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$product) redirect('products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description = $_POST['description'] ?? '';
    $regularPrice = (float)($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stockQty = (int)($_POST['stock_qty'] ?? 0);
    $stockStatus = in_array($_POST['stock_status'] ?? '', ['in_stock','out_of_stock','backorder']) ? $_POST['stock_status'] : 'in_stock';
    $status = in_array($_POST['status'] ?? '', ['active','inactive','draft']) ? $_POST['status'] : 'active';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isNew = isset($_POST['is_new_arrival']) ? 1 : 0;
    $isBest = isset($_POST['is_best_seller']) ? 1 : 0;
    $isTrending = isset($_POST['is_trending']) ? 1 : 0;
    $tags = trim($_POST['tags'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDescription = trim($_POST['seo_description'] ?? '');
    $seoKeywords = trim($_POST['seo_keywords'] ?? '');

    if ($name === '' || $sku === '' || !$categoryId || $regularPrice <= 0) {
        flash_set('danger', 'Please fill in all required fields (name, SKU, category, price).');
        redirect('product_form.php' . ($id ? "?id=$id" : ''));
    }

    $slug = unique_slug($mysqli, 'products', slugify($name), $id);

    if ($id) {
        $stmt = mysqli_prepare($mysqli, "UPDATE products SET category_id=?, brand_id=?, name=?, slug=?, sku=?, short_description=?, description=?, regular_price=?, sale_price=?, stock_qty=?, stock_status=?, status=?, is_featured=?, is_new_arrival=?, is_best_seller=?, is_trending=?, tags=?, weight=?, dimensions=?, video_url=?, seo_title=?, seo_description=?, seo_keywords=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'iisssssddissiiiisssssssi',
            $categoryId, $brandId, $name, $slug, $sku, $shortDescription, $description,
            $regularPrice, $salePrice, $stockQty, $stockStatus, $status,
            $isFeatured, $isNew, $isBest, $isTrending, $tags, $weight, $dimensions, $videoUrl,
            $seoTitle, $seoDescription, $seoKeywords, $id);
        mysqli_stmt_execute($stmt);
    } else {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO products (category_id, brand_id, name, slug, sku, short_description, description, regular_price, sale_price, stock_qty, stock_status, status, is_featured, is_new_arrival, is_best_seller, is_trending, tags, weight, dimensions, video_url, seo_title, seo_description, seo_keywords) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iisssssddissiiiisssssss',
            $categoryId, $brandId, $name, $slug, $sku, $shortDescription, $description,
            $regularPrice, $salePrice, $stockQty, $stockStatus, $status,
            $isFeatured, $isNew, $isBest, $isTrending, $tags, $weight, $dimensions, $videoUrl,
            $seoTitle, $seoDescription, $seoKeywords);
        mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($mysqli);
    }

    // Gallery images
    if (!empty($_FILES['images']['name'][0])) {
        $count = count($_FILES['images']['name']);
        $hasPrimary = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM product_images WHERE product_id = $id AND is_primary = 1"));
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmpFile = ['name' => $_FILES['images']['name'][$i], 'type' => $_FILES['images']['type'][$i], 'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i], 'size' => $_FILES['images']['size'][$i]];
            $_FILES['__single'] = $tmpFile;
            $path = handle_upload('__single', 'products');
            if ($path) {
                $isPrimary = (!$hasPrimary && $i === 0) ? 1 : 0;
                $stmt = mysqli_prepare($mysqli, "INSERT INTO product_images (product_id, image_path, sort_order, is_primary) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'isii', $id, $path, $i, $isPrimary);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    // Remove selected images
    foreach ($_POST['remove_images'] ?? [] as $imgId) {
        mysqli_query($mysqli, "DELETE FROM product_images WHERE id = " . (int)$imgId . " AND product_id = $id");
    }
    // Set primary image
    if (!empty($_POST['primary_image'])) {
        mysqli_query($mysqli, "UPDATE product_images SET is_primary = 0 WHERE product_id = $id");
        mysqli_query($mysqli, "UPDATE product_images SET is_primary = 1 WHERE id = " . (int)$_POST['primary_image'] . " AND product_id = $id");
    }

    // Variations: rebuild
    mysqli_query($mysqli, "DELETE FROM product_variations WHERE product_id = $id");
    $varSkus = $_POST['var_sku'] ?? [];
    $varPrices = $_POST['var_price'] ?? [];
    $varStocks = $_POST['var_stock'] ?? [];
    $varValueIds = $_POST['var_value_ids'] ?? [];
    foreach ($varSkus as $i => $vSku) {
        $vSku = trim($vSku);
        if ($vSku === '') continue;
        $vPrice = $varPrices[$i] !== '' ? (float)$varPrices[$i] : null;
        $vStock = (int)($varStocks[$i] ?? 0);
        $stmt = mysqli_prepare($mysqli, "INSERT INTO product_variations (product_id, sku, price, stock_qty) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'isdi', $id, $vSku, $vPrice, $vStock);
        mysqli_stmt_execute($stmt);
        $variationId = mysqli_insert_id($mysqli);
        $valueIds = array_filter(array_map('intval', explode(',', $varValueIds[$i] ?? '')));
        foreach ($valueIds as $vid) {
            mysqli_query($mysqli, "INSERT IGNORE INTO product_variation_values (variation_id, attribute_value_id) VALUES ($variationId, $vid)");
        }
    }

    flash_set('success', 'Product saved successfully.');
    redirect('product_form.php?id=' . $id);
}

$categories = mysqli_query($mysqli, "SELECT * FROM categories ORDER BY parent_id IS NULL DESC, name");
$brands = mysqli_query($mysqli, "SELECT * FROM brands WHERE status='active' ORDER BY name");
$attributes = mysqli_query($mysqli, "SELECT * FROM attributes ORDER BY name");
$attributesData = [];
mysqli_data_seek($attributes, 0);
while ($a = mysqli_fetch_assoc($attributes)) {
    $valuesRes = mysqli_query($mysqli, "SELECT * FROM attribute_values WHERE attribute_id = " . (int)$a['id']);
    $vals = [];
    while ($v = mysqli_fetch_assoc($valuesRes)) $vals[] = $v;
    $a['values'] = $vals;
    $attributesData[] = $a;
}
mysqli_data_seek($categories, 0);

$images = [];
$variations = [];
if ($id) {
    $imgRes = mysqli_query($mysqli, "SELECT * FROM product_images WHERE product_id = $id ORDER BY sort_order");
    while ($r = mysqli_fetch_assoc($imgRes)) $images[] = $r;

    $varRes = mysqli_query($mysqli, "SELECT * FROM product_variations WHERE product_id = $id");
    while ($v = mysqli_fetch_assoc($varRes)) {
        $labelRes = mysqli_query($mysqli, "SELECT av.value, av.id FROM product_variation_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id WHERE pvv.variation_id = " . (int)$v['id']);
        $labels = [];
        $ids = [];
        while ($l = mysqli_fetch_assoc($labelRes)) { $labels[] = $l['value']; $ids[] = $l['id']; }
        $v['label'] = implode(' / ', $labels);
        $v['value_ids'] = implode(',', $ids);
        $variations[] = $v;
    }
}
?>
<h1 class="page-title mb-4"><?= $id ? 'Edit Product' : 'Add Product' ?></h1>

<form method="post" enctype="multipart/form-data" id="productForm">
<?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">General Information</h2>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" required value="<?= e($product['name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">SKU *</label><input type="text" name="sku" class="form-control" required value="<?= e($product['sku'] ?? '') ?>"></div>
        <div class="col-md-6">
          <label class="form-label">Category *</label>
          <select name="category_id" class="form-select select2" required>
            <option value="">Select category</option>
            <?php while ($c = mysqli_fetch_assoc($categories)): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (($product['category_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>><?= $c['parent_id'] ? '&mdash; ' : '' ?><?= e($c['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Brand</label>
          <select name="brand_id" class="form-select select2">
            <option value="">None</option>
            <?php mysqli_data_seek($brands, 0); while ($b = mysqli_fetch_assoc($brands)): ?>
              <option value="<?= (int)$b['id'] ?>" <?= (($product['brand_id'] ?? 0) == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-12"><label class="form-label">Short Description</label><textarea name="short_description" class="form-control" rows="2"><?= e($product['short_description'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">Full Description</label><textarea name="description" class="form-control richtext" rows="6"><?= $product['description'] ?? '' ?></textarea></div>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Pricing & Stock</h2>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Regular Price (Rs.) *</label><input type="number" step="0.01" name="regular_price" class="form-control" required value="<?= e($product['regular_price'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Sale Price (Rs.)</label><input type="number" step="0.01" name="sale_price" class="form-control" value="<?= e($product['sale_price'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Stock Quantity</label><input type="number" name="stock_qty" class="form-control" value="<?= e($product['stock_qty'] ?? 0) ?>"></div>
        <div class="col-md-4">
          <label class="form-label">Stock Status</label>
          <select name="stock_status" class="form-select">
            <?php foreach (['in_stock'=>'In Stock','out_of_stock'=>'Out of Stock','backorder'=>'Backorder'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= (($product['stock_status'] ?? 'in_stock')===$k)?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label">Weight</label><input type="text" name="weight" class="form-control" value="<?= e($product['weight'] ?? '') ?>" placeholder="e.g. 0.5kg"></div>
        <div class="col-md-4"><label class="form-label">Dimensions</label><input type="text" name="dimensions" class="form-control" value="<?= e($product['dimensions'] ?? '') ?>" placeholder="LxWxH cm"></div>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Images</h2>
      <?php if ($images): ?>
      <div class="d-flex flex-wrap gap-3 mb-3">
        <?php foreach ($images as $img): ?>
          <div class="text-center">
            <img src="<?= e(BASE_URL.'/'.$img['image_path']) ?>" class="thumb-preview mb-1">
            <div class="form-check small"><input class="form-check-input" type="radio" name="primary_image" value="<?= (int)$img['id'] ?>" <?= $img['is_primary'] ? 'checked' : '' ?>> <label class="form-check-label">Primary</label></div>
            <div class="form-check small"><input class="form-check-input" type="checkbox" name="remove_images[]" value="<?= (int)$img['id'] ?>"> <label class="form-check-label text-danger">Remove</label></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <label class="form-label">Upload Images (gallery, multiple allowed)</label>
      <input type="file" name="images[]" class="form-control" multiple accept="image/*">
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Variations</h2>
      <p class="text-muted small">Select attribute values and click "Generate" to create variation rows, then set SKU, price override and stock for each.</p>
      <div class="row g-3 mb-3">
        <?php foreach ($attributesData as $attr): ?>
          <div class="col-md-4">
            <label class="form-label"><?= e($attr['name']) ?></label>
            <select multiple class="form-select attr-select" data-attr="<?= (int)$attr['id'] ?>" size="4">
              <?php foreach ($attr['values'] as $v): ?>
                <option value="<?= (int)$v['id'] ?>" data-label="<?= e($v['value']) ?>"><?= e($v['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-dark btn-sm mb-3" id="genVariations">Generate Variations</button>
      <div class="table-responsive">
      <table class="table table-sm" id="variationTable">
        <thead><tr><th>Combination</th><th>SKU</th><th>Price Override</th><th>Stock</th><th></th></tr></thead>
        <tbody id="variationRows">
          <?php foreach ($variations as $i => $v): ?>
          <tr>
            <td><?= e($v['label']) ?><input type="hidden" name="var_value_ids[]" value="<?= e($v['value_ids']) ?>"></td>
            <td><input type="text" name="var_sku[]" class="form-control form-control-sm" value="<?= e($v['sku']) ?>"></td>
            <td><input type="number" step="0.01" name="var_price[]" class="form-control form-control-sm" value="<?= e($v['price'] ?? '') ?>"></td>
            <td><input type="number" name="var_stock[]" class="form-control form-control-sm" value="<?= (int)$v['stock_qty'] ?>"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-x"></i></button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Publish</h2>
      <label class="form-label">Status</label>
      <select name="status" class="form-select mb-3">
        <?php foreach (['active'=>'Active','inactive'=>'Inactive','draft'=>'Draft'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= (($product['status'] ?? 'active')===$k)?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="ff" <?= !empty($product['is_featured'])?'checked':'' ?>><label class="form-check-label" for="ff">Featured Product</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_new_arrival" id="fn" <?= !empty($product['is_new_arrival'])?'checked':'' ?>><label class="form-check-label" for="fn">New Arrival</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_best_seller" id="fb" <?= !empty($product['is_best_seller'])?'checked':'' ?>><label class="form-check-label" for="fb">Best Seller</label></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_trending" id="ft" <?= !empty($product['is_trending'])?'checked':'' ?>><label class="form-check-label" for="ft">Trending</label></div>
      <button class="btn btn-primary text-white w-100"><i class="bi bi-check-lg"></i> Save Product</button>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Tags & Video</h2>
      <label class="form-label">Tags (comma separated)</label>
      <input type="text" name="tags" class="form-control mb-3" value="<?= e($product['tags'] ?? '') ?>">
      <label class="form-label">Video URL</label>
      <input type="text" name="video_url" class="form-control" value="<?= e($product['video_url'] ?? '') ?>">
    </div>

    <div class="admin-card">
      <h2 class="h6 mb-3">SEO</h2>
      <label class="form-label">SEO Title</label>
      <input type="text" name="seo_title" class="form-control mb-3" value="<?= e($product['seo_title'] ?? '') ?>">
      <label class="form-label">SEO Description</label>
      <textarea name="seo_description" class="form-control mb-3" rows="2"><?= e($product['seo_description'] ?? '') ?></textarea>
      <label class="form-label">SEO Keywords</label>
      <input type="text" name="seo_keywords" class="form-control" value="<?= e($product['seo_keywords'] ?? '') ?>">
    </div>
  </div>
</div>
</form>

<script>
document.getElementById('genVariations').addEventListener('click', function () {
  var selects = document.querySelectorAll('.attr-select');
  var groups = [];
  selects.forEach(function (sel) {
    var opts = Array.from(sel.selectedOptions);
    if (opts.length) groups.push(opts.map(function (o) { return { id: o.value, label: o.dataset.label }; }));
  });
  if (!groups.length) { adminToast('Select at least one attribute value', 'warning'); return; }
  var combos = groups.reduce(function (acc, group) {
    var res = [];
    acc.forEach(function (a) { group.forEach(function (g) { res.push(a.concat([g])); }); });
    return res;
  }, [[]]);
  var tbody = document.getElementById('variationRows');
  combos.forEach(function (combo) {
    var label = combo.map(function (c) { return c.label; }).join(' / ');
    var ids = combo.map(function (c) { return c.id; }).join(',');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td>' + label + '<input type="hidden" name="var_value_ids[]" value="' + ids + '"></td>' +
      '<td><input type="text" name="var_sku[]" class="form-control form-control-sm"></td>' +
      '<td><input type="number" step="0.01" name="var_price[]" class="form-control form-control-sm"></td>' +
      '<td><input type="number" name="var_stock[]" class="form-control form-control-sm" value="0"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x"></i></button></td>';
    tbody.appendChild(tr);
  });
});
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
