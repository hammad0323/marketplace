<?php
$vendor = require_vendor();

if ($vendor['status'] !== 'approved') {
    flash('error', 'Your store must be approved by an admin before you can add products.');
    redirect('/vendor/dashboard');
}

$marketplaceSlug = find_marketplace_type($vendor['marketplace_type_id'])['slug'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($marketplaceSlug === 'business') {
        $approvedIds = approved_category_ids_for_vendor($vendor['id']);
        if (!in_array($categoryId, $approvedIds, true)) {
            flash('error', 'You are not approved to sell in that category yet.');
            redirect('/vendor/dashboard/products/create');
        }

        foreach (vendor_category_requests_for_vendor($vendor['id']) as $request) {
            if ((int) $request['category_id'] === $categoryId && $request['usage_limit'] !== null) {
                $used = count_vendor_products_in_category($vendor['id'], $categoryId);
                if ($used >= (int) $request['usage_limit']) {
                    flash('error', 'You have reached the product limit admin set for this category.');
                    redirect('/vendor/dashboard/products/create');
                }
            }
        }
    } else {
        // Artisan and Official vendors sell within their marketplace's
        // full category set once approved — only Business Shops go
        // through per-category approval.
        $validIds = filter_category_ids_by_marketplace([$categoryId], $vendor['marketplace_type_id']);
        if (!$validIds) {
            flash('error', 'Please choose a valid category.');
            redirect('/vendor/dashboard/products/create');
        }
    }

    $title = trim($_POST['title'] ?? '');
    if ($title === '' || $categoryId <= 0) {
        flash('error', 'Title and category are required.');
        redirect('/vendor/dashboard/products/create');
    }

    $slugBase = slugify($title);
    $newSlug = $slugBase;
    $suffix = 1;
    while (find_product_by_slug($newSlug)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $images = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));

    insert_product([
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

    flash('success', 'Product added.');
    redirect('/vendor/dashboard/products');
}

if ($marketplaceSlug === 'business') {
    $approvedIds = approved_category_ids_for_vendor($vendor['id']);
    $categories = $approvedIds
        ? array_filter(all_categories_by_marketplace($vendor['marketplace_type_id']), fn ($c) => in_array($c['id'], $approvedIds, true))
        : [];
} else {
    $categories = active_categories_by_marketplace($vendor['marketplace_type_id']);
}

$pageTitle = 'Add Product';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<div class="form-card form-card-wide">
    <h1>Add Product</h1>

    <?php if (!$categories): ?>
        <p>You don't have any approved selling categories yet.
           <a href="/vendor/dashboard/categories">Request one</a> and wait for admin approval.</p>
    <?php else: ?>
        <form method="post" action="/vendor/dashboard/products/create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="title">Product Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
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

<?php require __DIR__ . '/../../partials/footer.php'; ?>
