<?php
require __DIR__ . '/config.php';

$vendor = mp_require_vendor();

if ($vendor['status'] !== 'approved') {
    mp_flash('error', 'Your store must be approved by an admin before you can add products.');
    mp_redirect('/vendor-dashboard.php');
}

$marketplaceSlug = mp_find_marketplace_type($vendor['marketplace_type_id'])['slug'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($marketplaceSlug === 'business') {
        $approvedIds = mp_approved_category_ids_for_vendor($vendor['id']);
        if (!in_array($categoryId, $approvedIds, true)) {
            mp_flash('error', 'You are not approved to sell in that category yet.');
            mp_redirect('/vendor-product-form.php');
        }

        foreach (mp_vendor_category_requests_for_vendor($vendor['id']) as $request) {
            if ((int) $request['category_id'] === $categoryId && $request['usage_limit'] !== null) {
                $used = mp_count_vendor_products_in_category($vendor['id'], $categoryId);
                if ($used >= (int) $request['usage_limit']) {
                    mp_flash('error', 'You have reached the product limit admin set for this category.');
                    mp_redirect('/vendor-product-form.php');
                }
            }
        }
    } else {
        // Artisan and Official vendors sell within their marketplace's
        // full category set once approved — only Business Shops go
        // through per-category approval.
        $validIds = mp_filter_category_ids_by_marketplace([$categoryId], $vendor['marketplace_type_id']);
        if (!$validIds) {
            mp_flash('error', 'Please choose a valid category.');
            mp_redirect('/vendor-product-form.php');
        }
    }

    $title = trim($_POST['title'] ?? '');
    if ($title === '' || $categoryId <= 0) {
        mp_flash('error', 'Title and category are required.');
        mp_redirect('/vendor-product-form.php');
    }

    $slugBase = mp_slugify($title);
    $newSlug = $slugBase;
    $suffix = 1;
    while (mp_find_product_by_slug($newSlug)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $images = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));

    mp_insert_product([
        'vendor_id'           => $vendor['id'],
        'category_id'         => $categoryId,
        'marketplace_type_id' => $vendor['marketplace_type_id'],
        'title'               => $title,
        'slug'                => $newSlug,
        'description'         => trim($_POST['description'] ?? ''),
        'price'               => (float) ($_POST['price'] ?? 0),
        'images'              => json_encode(array_values($images)),
        'status'              => 'published',
    ]);

    mp_flash('success', 'Product added.');
    mp_redirect('/vendor-products.php');
}

if ($marketplaceSlug === 'business') {
    $approvedIds = mp_approved_category_ids_for_vendor($vendor['id']);
    $categories = $approvedIds
        ? array_filter(mp_all_categories_by_marketplace($vendor['marketplace_type_id']), fn ($c) => in_array($c['id'], $approvedIds, true))
        : [];
} else {
    $categories = mp_active_categories_by_marketplace($vendor['marketplace_type_id']);
}

$pageTitle = 'Add Product';
$theme = 'main';
require __DIR__ . '/header.php';
?>

<div class="form-card form-card-wide reveal">
    <h1>Add Product</h1>

    <?php if (!$categories): ?>
        <p>You don't have any approved selling categories yet.
           <a href="/vendor-categories.php">Request one</a> and wait for admin approval.</p>
    <?php else: ?>
        <form method="post" action="/vendor-product-form.php">
            <?= mp_csrf_field() ?>
            <div class="form-group">
                <label for="title">Product Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= mp_e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="image_urls">Image URLs (one per line)</label>
                <textarea id="image_urls" name="image_urls" rows="3"></textarea>
                <small>File upload isn't wired up yet — paste hosted image URLs for now.</small>
            </div>
            <button type="submit" class="btn">Publish Product</button>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
