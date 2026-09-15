<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $keys = [
        'store_name','store_tagline','store_email','store_phone','store_address','currency_symbol',
        'tax_percent','google_client_id','google_client_secret',
        'smtp_host','smtp_port','smtp_username','smtp_password','smtp_from_email',
        'seo_default_title','seo_default_description',
    ];
    foreach ($keys as $k) {
        set_setting($k, trim($_POST[$k] ?? ''));
    }
    set_setting('guest_checkout_enabled', isset($_POST['guest_checkout_enabled']) ? '1' : '0');
    set_setting('google_login_enabled', isset($_POST['google_login_enabled']) ? '1' : '0');
    set_setting('reviews_enabled', isset($_POST['reviews_enabled']) ? '1' : '0');
    flash_set('success', 'Settings saved.');
    redirect('settings.php');
}
?>
<h1 class="page-title mb-4">Settings</h1>
<form method="post">
<?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Store Information</h2>
      <label class="form-label">Store Name</label><input type="text" name="store_name" class="form-control mb-3" value="<?= e(get_setting('store_name')) ?>">
      <label class="form-label">Tagline</label><input type="text" name="store_tagline" class="form-control mb-3" value="<?= e(get_setting('store_tagline')) ?>">
      <label class="form-label">Support Email</label><input type="email" name="store_email" class="form-control mb-3" value="<?= e(get_setting('store_email')) ?>">
      <label class="form-label">Phone</label><input type="text" name="store_phone" class="form-control mb-3" value="<?= e(get_setting('store_phone')) ?>">
      <label class="form-label">Address</label><textarea name="store_address" class="form-control mb-3" rows="2"><?= e(get_setting('store_address')) ?></textarea>
      <label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= e(get_setting('currency_symbol')) ?>">
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Checkout & Tax</h2>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" name="guest_checkout_enabled" <?= get_setting('guest_checkout_enabled')==='1'?'checked':'' ?>>
        <label class="form-check-label">Enable Guest Checkout</label>
      </div>
      <label class="form-label">Tax Percentage (%)</label>
      <input type="number" step="0.01" name="tax_percent" class="form-control" value="<?= e(get_setting('tax_percent')) ?>">
    </div>

    <div class="admin-card">
      <h2 class="h6 mb-3">Reviews</h2>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="reviews_enabled" <?= get_setting('reviews_enabled')==='1'?'checked':'' ?>>
        <label class="form-check-label">Allow customer reviews on products</label>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Google Login</h2>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" name="google_login_enabled" <?= get_setting('google_login_enabled')==='1'?'checked':'' ?>>
        <label class="form-check-label">Enable "Continue with Google"</label>
      </div>
      <label class="form-label">Google Client ID</label><input type="text" name="google_client_id" class="form-control mb-3" value="<?= e(get_setting('google_client_id')) ?>">
      <label class="form-label">Google Client Secret</label><input type="text" name="google_client_secret" class="form-control" value="<?= e(get_setting('google_client_secret')) ?>">
      <p class="small text-muted mt-2 mb-0">Set the OAuth redirect URI in Google Cloud Console to: <code><?= e(BASE_URL) ?>/google_callback.php</code></p>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Email / SMTP</h2>
      <label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control mb-3" value="<?= e(get_setting('smtp_host')) ?>">
      <label class="form-label">SMTP Port</label><input type="text" name="smtp_port" class="form-control mb-3" value="<?= e(get_setting('smtp_port')) ?>">
      <label class="form-label">SMTP Username</label><input type="text" name="smtp_username" class="form-control mb-3" value="<?= e(get_setting('smtp_username')) ?>">
      <label class="form-label">SMTP Password</label><input type="password" name="smtp_password" class="form-control mb-3" value="<?= e(get_setting('smtp_password')) ?>">
      <label class="form-label">From Email</label><input type="email" name="smtp_from_email" class="form-control" value="<?= e(get_setting('smtp_from_email')) ?>">
    </div>

    <div class="admin-card">
      <h2 class="h6 mb-3">Default SEO</h2>
      <label class="form-label">Default Meta Title</label><input type="text" name="seo_default_title" class="form-control mb-3" value="<?= e(get_setting('seo_default_title')) ?>">
      <label class="form-label">Default Meta Description</label><textarea name="seo_default_description" class="form-control" rows="2"><?= e(get_setting('seo_default_description')) ?></textarea>
    </div>
  </div>
</div>
<button class="btn btn-primary text-white mt-4"><i class="bi bi-check-lg"></i> Save Settings</button>
</form>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
