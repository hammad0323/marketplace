<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'general';

    if ($action === 'send_test_email') {
        require_once __DIR__ . '/../includes/mailer.php';
        $to = trim($_POST['test_email'] ?? '');
        if ($to) {
            $sent = send_email($to, 'Test Email from ' . get_setting('store_name'), '<p>This is a test email confirming your SMTP settings are working correctly.</p>');
            flash_set($sent ? 'success' : 'danger', $sent ? 'Test email sent successfully.' : 'Could not send test email. Check SMTP settings and logs/mail.log.');
        }
        redirect('settings.php');
    }

    $keys = [
        'store_name','store_tagline','store_email','store_phone','store_address','currency_symbol',
        'tax_percent','google_client_id','google_client_secret',
        'smtp_host','smtp_port','smtp_username','smtp_password','smtp_from_email','smtp_from_name','smtp_encryption',
        'seo_default_title','seo_default_description','google_site_verification','bing_site_verification','social_twitter_handle',
        'theme_accent_color','theme_dark_color',
        'ads_txt_content','robots_extra_rules',
    ];
    foreach ($keys as $k) {
        set_setting($k, trim($_POST[$k] ?? ''));
    }
    set_setting('guest_checkout_enabled', isset($_POST['guest_checkout_enabled']) ? '1' : '0');
    set_setting('google_login_enabled', isset($_POST['google_login_enabled']) ? '1' : '0');
    set_setting('reviews_enabled', isset($_POST['reviews_enabled']) ? '1' : '0');
    set_setting('order_emails_enabled', isset($_POST['order_emails_enabled']) ? '1' : '0');

    $logo = handle_upload('site_logo', 'general');
    if ($logo) set_setting('site_logo', $logo);
    $favicon = handle_upload('site_favicon', 'general');
    if ($favicon) set_setting('site_favicon', $favicon);
    $ogImage = handle_upload('site_og_image', 'general');
    if ($ogImage) set_setting('site_og_image', $ogImage);

    flash_set('success', 'Settings saved.');
    redirect('settings.php');
}
?>
<h1 class="page-title mb-4">Settings</h1>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="action" value="general">
<div class="row g-4">
  <div class="col-lg-6">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Branding</h2>
      <label class="form-label">Store Name</label><input type="text" name="store_name" class="form-control mb-3" value="<?= e(get_setting('store_name')) ?>">
      <label class="form-label">Tagline</label><input type="text" name="store_tagline" class="form-control mb-3" value="<?= e(get_setting('store_tagline')) ?>">
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Logo</label>
          <input type="file" name="site_logo" class="form-control" accept="image/*">
          <?php if (get_setting('site_logo')): ?><img src="<?= e(BASE_URL.'/'.get_setting('site_logo')) ?>" class="thumb-preview mt-2" style="max-height:60px;width:auto"><?php endif; ?>
        </div>
        <div class="col-6">
          <label class="form-label">Site Icon (favicon)</label>
          <input type="file" name="site_favicon" class="form-control" accept="image/*">
          <?php if (get_setting('site_favicon')): ?><img src="<?= e(BASE_URL.'/'.get_setting('site_favicon')) ?>" class="thumb-preview mt-2" style="max-height:40px;width:auto"><?php endif; ?>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Accent Color</label>
          <input type="color" name="theme_accent_color" class="form-control form-control-color w-100" value="<?= e(get_setting('theme_accent_color', '#a5763f')) ?>">
        </div>
        <div class="col-6">
          <label class="form-label">Dark / Ink Color</label>
          <input type="color" name="theme_dark_color" class="form-control form-control-color w-100" value="<?= e(get_setting('theme_dark_color', '#211d17')) ?>">
        </div>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Contact Information</h2>
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
      <p class="small text-muted mt-2 mb-0">Set the OAuth redirect URI in Google Cloud Console to: <code><?= e(url('google-callback')) ?></code></p>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Email / SMTP</h2>
      <label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control mb-3" value="<?= e(get_setting('smtp_host')) ?>" placeholder="smtp.gmail.com">
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label">SMTP Port</label><input type="text" name="smtp_port" class="form-control" value="<?= e(get_setting('smtp_port', '587')) ?>"></div>
        <div class="col-6"><label class="form-label">Encryption</label>
          <select name="smtp_encryption" class="form-select">
            <?php foreach (['tls'=>'STARTTLS','ssl'=>'SSL','none'=>'None'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= get_setting('smtp_encryption','tls')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="form-label">SMTP Username</label><input type="text" name="smtp_username" class="form-control mb-3" value="<?= e(get_setting('smtp_username')) ?>">
      <label class="form-label">SMTP Password</label><input type="password" name="smtp_password" class="form-control mb-3" value="<?= e(get_setting('smtp_password')) ?>">
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label">From Email</label><input type="email" name="smtp_from_email" class="form-control" value="<?= e(get_setting('smtp_from_email')) ?>"></div>
        <div class="col-6"><label class="form-label">From Name</label><input type="text" name="smtp_from_name" class="form-control" value="<?= e(get_setting('smtp_from_name') ?: get_setting('store_name')) ?>"></div>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" name="order_emails_enabled" <?= get_setting('order_emails_enabled','1')==='1'?'checked':'' ?>>
        <label class="form-check-label">Send order confirmation &amp; status emails automatically</label>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">SEO Defaults</h2>
      <label class="form-label">Default Meta Title</label><input type="text" name="seo_default_title" class="form-control mb-3" value="<?= e(get_setting('seo_default_title')) ?>">
      <label class="form-label">Default Meta Description</label><textarea name="seo_default_description" class="form-control mb-3" rows="2"><?= e(get_setting('seo_default_description')) ?></textarea>
      <label class="form-label">Default Social Share Image (og:image)</label>
      <input type="file" name="site_og_image" class="form-control mb-2" accept="image/*">
      <?php if (get_setting('site_og_image')): ?><img src="<?= e(BASE_URL.'/'.get_setting('site_og_image')) ?>" class="thumb-preview mb-2"><?php endif; ?>
      <div class="row g-3">
        <div class="col-6"><label class="form-label">Google Site Verification</label><input type="text" name="google_site_verification" class="form-control" value="<?= e(get_setting('google_site_verification')) ?>"></div>
        <div class="col-6"><label class="form-label">Bing Site Verification</label><input type="text" name="bing_site_verification" class="form-control" value="<?= e(get_setting('bing_site_verification')) ?>"></div>
      </div>
      <label class="form-label mt-3">Twitter Handle</label><input type="text" name="social_twitter_handle" class="form-control" placeholder="@yourbrand" value="<?= e(get_setting('social_twitter_handle')) ?>">
    </div>

    <div class="admin-card">
      <h2 class="h6 mb-3">robots.txt &amp; ads.txt</h2>
      <label class="form-label">Extra robots.txt rules</label>
      <textarea name="robots_extra_rules" class="form-control mb-3" rows="3" placeholder="Disallow: /some-path/"><?= e(get_setting('robots_extra_rules')) ?></textarea>
      <label class="form-label">ads.txt content</label>
      <textarea name="ads_txt_content" class="form-control" rows="4" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"><?= e(get_setting('ads_txt_content')) ?></textarea>
    </div>
  </div>
</div>
<button class="btn btn-primary text-white mt-4"><i class="bi bi-check-lg"></i> Save Settings</button>
</form>

<div class="admin-card mt-4">
  <h2 class="h6 mb-3">Send a Test Email</h2>
  <form method="post" class="row g-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="send_test_email">
    <div class="col-auto"><input type="email" name="test_email" class="form-control" placeholder="you@example.com" required></div>
    <div class="col-auto"><button class="btn btn-outline-dark">Send Test Email</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
