<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$products = mp_all_products_admin();

$pageTitle = 'Products';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Products</h1>
<p style="color:var(--ink-500); margin-top:-.5rem;"><?= count($products) ?> product(s) across every vendor.</p>

<div class="admin-panel">
    <?php if (!$products): ?>
        <p>No products yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Product</th><th>Vendor</th><th>Marketplace</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= mp_e($product['title']) ?></td>
                    <td><?= mp_e($product['store_name']) ?></td>
                    <td><span class="badge"><?= mp_e($product['badge_label']) ?></span></td>
                    <td><?= mp_e($product['category_name']) ?></td>
                    <td><?= mp_currency((float) $product['price']) ?></td>
                    <td><?= (int) $product['stock_quantity'] ?></td>
                    <td>
                        <form method="post" action="product-toggle-status.php" class="inline-form">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="status-chip status-<?= $product['status'] === 'published' ? 'completed' : 'pending' ?>" style="border:none; cursor:pointer;">
                                <?= mp_e(ucfirst($product['status'])) ?>
                            </button>
                        </form>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="product-edit.php?id=<?= (int) $product['id'] ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <form method="post" action="product-delete.php" class="inline-form" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="link-button" style="color:var(--danger);">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
