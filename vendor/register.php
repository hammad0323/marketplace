<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $storeName = trim($_POST['store_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $vendorTypeSlug = $_POST['vendor_type'] ?? '';
    $requestedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []);

    if ($storeName === '' || $email === '' || strlen($password) < 8 || !in_array($vendorTypeSlug, ['artisan', 'business'], true)) {
        mp_flash('error', 'Please fill in all required fields (password must be at least 8 characters).');
        mp_redirect('register.php');
    }

    if (mp_find_vendor_by_email($email)) {
        mp_flash('error', 'An account with that email already exists.');
        mp_redirect('register.php');
    }

    $marketplaceType = mp_find_marketplace_type_by_slug($vendorTypeSlug);
    $slugBase = mp_slugify($storeName);
    $newSlug = $slugBase;
    $suffix = 1;
    while (mp_find_vendor_by_slug($newSlug)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $vendorId = mp_insert_vendor([
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
        $validCategoryIds = mp_filter_category_ids_by_marketplace($requestedCategoryIds, $marketplaceType['id']);
        if ($validCategoryIds) {
            mp_request_vendor_categories($vendorId, $validCategoryIds);
        }
    }

    mp_notify('vendor.welcome', $email, ['store_name' => $storeName]);
    mp_notify('admin.new_vendor_registration', 'admin@marketplace.test', ['vendor_id' => $vendorId, 'store_name' => $storeName]);

    mp_login_vendor(['id' => $vendorId]);

    mp_flash('success', 'Registration received! Your store is pending admin approval — you can complete your profile in the meantime.');
    mp_redirect('dashboard.php');
}

$artisanType = mp_find_marketplace_type_by_slug('artisan');
$businessType = mp_find_marketplace_type_by_slug('business');
$artisanCategories = mp_active_categories_by_marketplace($artisanType['id']);
$businessCategories = mp_active_categories_by_marketplace($businessType['id']);

$pageTitle = 'Become a Vendor';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card form-card-wide reveal">
    <h1>Become a Vendor</h1>
    <p>Choose the marketplace you want to sell in. Your store stays in <strong>pending</strong>
       status — you can complete your profile right away, but products and orders unlock only
       after an admin approves your application.</p>

    <form method="post" action="register.php">
        <?= mp_csrf_field() ?>

        <div class="form-group">
            <label>Vendor Type</label>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="artisan" onclick="toggleCategories()" checked> Artisan (handmade creator)</label><br>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="business" onclick="toggleCategories()"> Business Shop (retail store)</label>
        </div>

        <div class="form-group">
            <label for="store_name">Store Name</label>
            <input type="text" id="store_name" name="store_name" required value="<?= mp_e(mp_old('store_name')) ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= mp_e(mp_old('email')) ?>">
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
                    <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= mp_e($category['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn">Create My Store</button>
    </form>

    <p style="margin-top:1rem;">Already have a store? <a href="login.php">Log in</a></p>
</div>

<script>
function toggleCategories() {
    var isBusiness = document.querySelector('input[name="vendor_type"]:checked').value === 'business';
    document.getElementById('business-categories').style.display = isBusiness ? 'block' : 'none';
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
