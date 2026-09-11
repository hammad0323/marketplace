<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

if (isset($_GET['delete'])) {
    tp_execute('DELETE FROM crm_products WHERE id = ? AND business_id = ?', 'ii', [(int) $_GET['delete'], $bid]);
    tp_flash_set('success', 'Product deleted.');
    header('Location: ' . tp_url('crm/products.php'));
    exit;
}

$products = tp_query('SELECT * FROM crm_products WHERE business_id = ? ORDER BY name', 'i', [$bid]);

if (!empty($_GET['print'])) {
    $business = tp_current_business();
    ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>Price List — <?= e($business['business_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>body{padding:2rem;} @media print { .no-print{display:none;} }</style>
    </head><body>
    <div class="no-print mb-3"><button class="btn btn-primary" onclick="window.print()">Print</button></div>
    <h1 class="h4 fw-bold"><?= e($business['business_name']) ?> — Price List</h1>
    <p class="text-muted"><?= date('F j, Y') ?></p>
    <table class="table table-bordered">
      <thead><tr><th>Product</th><th>SKU</th><th>Retail Price</th><th>Wholesale Price</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td><?= e($p['sku']) ?></td><td><?= crm_currency_symbol() . number_format((float) $p['retail_price'], 2) ?></td><td><?= crm_currency_symbol() . number_format((float) $p['wholesale_price'], 2) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </body></html>
    <?php
    exit;
}

$crmPageTitle = 'Products & Pricing';
$crmActive = 'products';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="<?= tp_url('crm/products.php?print=1') ?>" target="_blank" class="tp-btn tp-btn-sm tp-btn-light"><i class="bi bi-printer"></i> Print Price List</a>
  <a href="<?= tp_url('crm/product-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> Add Product</a>
</div>

<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Name</th><th>SKU</th><th>Cost</th><th>Wholesale</th><th>Retail</th><th>Stock</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): $low = $p['stock_qty'] <= $p['low_stock_threshold']; ?>
      <tr>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['sku']) ?></td>
        <td><?= crm_currency_symbol() . number_format((float) $p['cost_price'], 2) ?></td>
        <td><?= crm_currency_symbol() . number_format((float) $p['wholesale_price'], 2) ?></td>
        <td><?= crm_currency_symbol() . number_format((float) $p['retail_price'], 2) ?></td>
        <td class="<?= $low ? 'text-danger fw-bold' : '' ?>"><?= number_format((float) $p['stock_qty'], 0) ?> <?= e($p['unit']) ?><?= $low ? ' <i class="bi bi-exclamation-triangle" title="Low stock"></i>' : '' ?></td>
        <td>
          <a href="<?= tp_url('crm/product-form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <a href="<?= tp_url('crm/products.php?delete=' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" data-crm-confirm-delete="<?= e($p['name']) ?>">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No products yet. <a href="<?= tp_url('crm/product-form.php') ?>">Add your first one</a>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
