<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $storeName = trim($_POST['store_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $vendorTypeSlug = $_POST['vendor_type'] ?? '';
    $requestedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []);

    if ($storeName === '' || $email === '' || strlen($password) < 8 || !in_array($vendorTypeSlug, ['artisan', 'business'], true)) {
        flash('error', 'Please fill in all required fields (password must be at least 8 characters).');
        redirect('/vendor/register');
    }

    if (find_vendor_by_email($email)) {
        flash('error', 'An account with that email already exists.');
        redirect('/vendor/register');
    }

    $marketplaceType = find_marketplace_type_by_slug($vendorTypeSlug);
    $slugBase = slugify($storeName);
    $newSlug = $slugBase;
    $suffix = 1;
    while (find_vendor_by_slug($newSlug)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $vendorId = insert_vendor([
        'marketplace_type_id' => $marketplaceType['id'],
        'store_name'          => $storeName,
        'slug'                => $newSlug,
        'email'               => $email,
        'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
        'phone'               => trim($_POST['phone'] ?? '') ?: null,
        'status'              => 'pending',
    ]);

    // Business vendors request the categories they want to sell in; an
    // admin must approve each one before products can use them.
    // Categories share one ID space across marketplace types, so the
    // submitted IDs are filtered down to ones that actually belong to
    // the business marketplace before being stored.
    if ($vendorTypeSlug === 'business' && $requestedCategoryIds) {
        $validCategoryIds = filter_category_ids_by_marketplace($requestedCategoryIds, $marketplaceType['id']);
        if ($validCategoryIds) {
            request_vendor_categories($vendorId, $validCategoryIds);
        }
    }

    notify('vendor.welcome', $email, ['store_name' => $storeName]);
    notify('admin.new_vendor_registration', 'admin@marketplace.test', ['vendor_id' => $vendorId, 'store_name' => $storeName]);

    login_vendor(['id' => $vendorId]);

    flash('success', 'Registration received! Your store is pending admin approval — you can complete your profile in the meantime.');
    redirect('/vendor/dashboard');
}

$artisanType = find_marketplace_type_by_slug('artisan');
$businessType = find_marketplace_type_by_slug('business');
$artisanCategories = active_categories_by_marketplace($artisanType['id']);
$businessCategories = active_categories_by_marketplace($businessType['id']);

$pageTitle = 'Become a Vendor';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<div class="form-card form-card-wide">
    <h1>Become a Vendor</h1>
    <p>Choose the marketplace you want to sell in. Your store stays in <strong>pending</strong>
       status — you can complete your profile right away, but products and orders unlock only
       after an admin approves your application.</p>

    <form method="post" action="/vendor/register">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Vendor Type</label>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="artisan" onclick="toggleCategories()" checked> Artisan (handmade creator)</label><br>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="business" onclick="toggleCategories()"> Business Shop (retail store)</label>
        </div>

        <div class="form-group">
            <label for="store_name">Store Name</label>
            <input type="text" id="store_name" name="store_name" required value="<?= e(old('store_name')) ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e(old('email')) ?>">
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
            <small>At least 8 characters.</small>
        </div>

        <div class="form-group" id="business-categories" style="display:none;">
            <label>Which categories would you like to sell in?</label>
            <small>Each category you request needs separate admin approval before you can list products in it.</small>
            <div class="checkbox-grid">
                <?php foreach ($businessCategories as $category): ?>
                    <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= e($category['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn">Create My Store</button>
    </form>

    <p style="margin-top:1rem;">Already have a store? <a href="/vendor/login">Log in</a></p>
</div>

<script>
function toggleCategories() {
    var isBusiness = document.querySelector('input[name="vendor_type"]:checked').value === 'business';
    document.getElementById('business-categories').style.display = isBusiness ? 'block' : 'none';
}
</script>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
