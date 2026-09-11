<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$id = (int) ($_GET['id'] ?? 0);
$product = $id ? tp_query_one('SELECT * FROM crm_products WHERE id = ? AND business_id = ?', 'ii', [$id, $bid]) : null;
if ($id && !$product) {
    http_response_code(404);
    exit('Product not found.');
}

$error = null;
$old = $product ?? [
    'name' => '', 'sku' => '', 'unit' => 'pcs', 'cost_price' => 0, 'wholesale_price' => 0,
    'retail_price' => 0, 'dealer_price' => 0, 'distributor_price' => 0, 'stock_qty' => 0, 'low_stock_threshold' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $old['name'] = tp_sanitize_text($_POST['name'] ?? '', 160);
    $old['sku'] = tp_sanitize_text($_POST['sku'] ?? '', 60);
    $old['unit'] = tp_sanitize_text($_POST['unit'] ?? 'pcs', 30);
    foreach (['cost_price', 'wholesale_price', 'retail_price', 'dealer_price', 'distributor_price', 'stock_qty', 'low_stock_threshold'] as $field) {
        $old[$field] = (float) tp_sanitize_number($_POST[$field] ?? 0);
    }

    if ($old['name'] === '') {
        $error = 'Product name is required.';
    } else {
        if ($product) {
            tp_execute(
                'UPDATE crm_products SET name=?, sku=?, unit=?, cost_price=?, wholesale_price=?, retail_price=?, dealer_price=?, distributor_price=?, stock_qty=?, low_stock_threshold=? WHERE id=? AND business_id=?',
                'sssdddddddii',
                [$old['name'], $old['sku'], $old['unit'], $old['cost_price'], $old['wholesale_price'], $old['retail_price'], $old['dealer_price'], $old['distributor_price'], $old['stock_qty'], $old['low_stock_threshold'], $id, $bid]
            );
            tp_flash_set('success', 'Product updated.');
        } else {
            tp_execute(
                'INSERT INTO crm_products (business_id, name, sku, unit, cost_price, wholesale_price, retail_price, dealer_price, distributor_price, stock_qty, low_stock_threshold) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                'isssddddddd',
                [$bid, $old['name'], $old['sku'], $old['unit'], $old['cost_price'], $old['wholesale_price'], $old['retail_price'], $old['dealer_price'], $old['distributor_price'], $old['stock_qty'], $old['low_stock_threshold']]
            );
            tp_flash_set('success', 'Product added.');
        }
        header('Location: ' . tp_url('crm/products.php'));
        exit;
    }
}

$crmPageTitle = $product ? 'Edit Product' : 'Add Product';
$crmActive = 'products';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card" style="max-width:680px;">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= tp_csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required autofocus></div>
      <div class="col-md-4"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" value="<?= e($old['unit']) ?>" placeholder="pcs, kg, box"></div>
      <div class="col-md-6"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" value="<?= e($old['sku']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Cost Price</label><input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e((string) $old['cost_price']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Retail Price</label><input type="number" step="0.01" min="0" name="retail_price" class="form-control" value="<?= e((string) $old['retail_price']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Wholesale Price</label><input type="number" step="0.01" min="0" name="wholesale_price" class="form-control" value="<?= e((string) $old['wholesale_price']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Dealer Price</label><input type="number" step="0.01" min="0" name="dealer_price" class="form-control" value="<?= e((string) $old['dealer_price']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Distributor Price</label><input type="number" step="0.01" min="0" name="distributor_price" class="form-control" value="<?= e((string) $old['distributor_price']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Stock Quantity</label><input type="number" step="0.01" min="0" name="stock_qty" class="form-control" value="<?= e((string) $old['stock_qty']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Low Stock Alert Below</label><input type="number" step="0.01" min="0" name="low_stock_threshold" class="form-control" value="<?= e((string) $old['low_stock_threshold']) ?>"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit"><?= $product ? 'Save Changes' : 'Add Product' ?></button>
      <a href="<?= tp_url('crm/products.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
