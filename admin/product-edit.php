<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();

$product = mp_find_product((int) ($_GET['id'] ?? 0));
if (!$product) {
    require __DIR__ . '/../404.php';
    return;
}

$marketplaceType = mp_find_marketplace_type($product['marketplace_type_id']);
$categories = mp_all_categories_by_marketplace($product['marketplace_type_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $stock = max(0, (int) ($_POST['stock_quantity'] ?? 0));

    if ($title === '' || $categoryId <= 0) {
        mp_flash('error', 'Title and category are required.');
        mp_redirect('product-edit.php?id=' . $product['id']);
    }

    mp_update_product($product['id'], [
        'title'          => $title,
        'category_id'    => $categoryId,
        'description'    => trim($_POST['description'] ?? ''),
        'price'          => $price,
        'stock_quantity' => $stock,
        'sku'            => trim($_POST['sku'] ?? '') ?: null,
        'is_featured'    => isset($_POST['is_featured']) ? 1 : 0,
        'is_best_seller' => isset($_POST['is_best_seller']) ? 1 : 0,
        'status'         => in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : $product['status'],
    ]);

    mp_log_activity('admin', $admin['id'], 'product.updated', 'product', $product['id'], $title);
    mp_flash('success', 'Product updated.');
    mp_redirect('products.php');
}

$pageTitle = 'Edit Product';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Edit Product</h1>

<div class="admin-panel">
    <form method="post" action="product-edit.php?id=<?= (int) $product['id'] ?>">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required value="<?= mp_e($product['title']) ?>">
        </div>
        <div class="form-group">
            <label for="category_id">Category (<?= mp_e($marketplaceType['name']) ?>)</label>
            <select id="category_id" name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $category['id'] === (int) $product['category_id'] ? 'selected' : '' ?>><?= mp_e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="checkout-address-grid">
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= mp_e((string) $product['price']) ?>">
            </div>
            <div class="form-group">
                <label for="stock_quantity">Stock</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" required value="<?= (int) $product['stock_quantity'] ?>">
            </div>
            <div class="form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" value="<?= mp_e($product['sku'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= mp_e($product['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $product['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="checkbox-grid" style="grid-template-columns: 1fr 1fr;">
            <label><input type="checkbox" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?>> Featured</label>
            <label><input type="checkbox" name="is_best_seller" <?= $product['is_best_seller'] ? 'checked' : '' ?>> Best Seller</label>
        </div>
        <button type="submit" class="btn" style="margin-top:1.25rem;">Save Changes</button>
        <a class="btn btn-secondary" href="products.php" style="margin-top:1.25rem;">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
