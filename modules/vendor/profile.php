<?php
$vendor = require_vendor();
$marketplaceSlug = find_marketplace_type($vendor['marketplace_type_id'])['slug'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($marketplaceSlug === 'artisan') {
        save_artisan_profile($vendor['id'], [
            'biography'   => trim($_POST['biography'] ?? ''),
            'brand_story' => trim($_POST['brand_story'] ?? ''),
            'social_links' => [
                'instagram' => trim($_POST['instagram'] ?? ''),
                'facebook'  => trim($_POST['facebook'] ?? ''),
                'pinterest' => trim($_POST['pinterest'] ?? ''),
            ],
        ]);
    } elseif ($marketplaceSlug === 'business') {
        save_business_profile($vendor['id'], [
            'business_info'   => trim($_POST['business_info'] ?? ''),
            'contact_email'   => trim($_POST['contact_email'] ?? ''),
            'contact_phone'   => trim($_POST['contact_phone'] ?? ''),
            'contact_address' => trim($_POST['contact_address'] ?? ''),
            'shop_policies'   => trim($_POST['shop_policies'] ?? ''),
            'delivery_info'   => trim($_POST['delivery_info'] ?? ''),
        ]);
    }

    flash('success', 'Profile updated.');
    redirect('/vendor/dashboard/profile');
}

$artisanProfile = $marketplaceSlug === 'artisan' ? find_artisan_profile($vendor['id']) : null;
$businessProfile = $marketplaceSlug === 'business' ? find_business_profile($vendor['id']) : null;

$pageTitle = 'Store Profile';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<h1>Store Profile</h1>

<?php if ($marketplaceSlug === 'artisan'): ?>
<form method="post" action="/vendor/dashboard/profile" class="content-panel">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="biography">Artist Biography</label>
        <textarea id="biography" name="biography" rows="4"><?= e($artisanProfile['biography'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="brand_story">Story Behind the Brand</label>
        <textarea id="brand_story" name="brand_story" rows="4"><?= e($artisanProfile['brand_story'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="instagram">Instagram URL</label>
        <input type="url" id="instagram" name="instagram" value="<?= e($artisanProfile['social_links']['instagram'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="facebook">Facebook URL</label>
        <input type="url" id="facebook" name="facebook" value="<?= e($artisanProfile['social_links']['facebook'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="pinterest">Pinterest URL</label>
        <input type="url" id="pinterest" name="pinterest" value="<?= e($artisanProfile['social_links']['pinterest'] ?? '') ?>">
    </div>
    <button type="submit" class="btn">Save Profile</button>
</form>

<?php else: ?>
<form method="post" action="/vendor/dashboard/profile" class="content-panel">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="business_info">Business Information</label>
        <textarea id="business_info" name="business_info" rows="4"><?= e($businessProfile['business_info'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="contact_email">Contact Email</label>
        <input type="email" id="contact_email" name="contact_email" value="<?= e($businessProfile['contact_email'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="contact_phone">Contact Phone</label>
        <input type="text" id="contact_phone" name="contact_phone" value="<?= e($businessProfile['contact_phone'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="contact_address">Contact Address</label>
        <input type="text" id="contact_address" name="contact_address" value="<?= e($businessProfile['contact_address'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="shop_policies">Shop Policies</label>
        <textarea id="shop_policies" name="shop_policies" rows="3"><?= e($businessProfile['shop_policies'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="delivery_info">Delivery Information</label>
        <textarea id="delivery_info" name="delivery_info" rows="3"><?= e($businessProfile['delivery_info'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn">Save Profile</button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
