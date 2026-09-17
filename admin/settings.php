<?php
require __DIR__ . '/../config.php';
wh_require_page_access('settings');
$businessId = wh_current_business_id();

$textFields = [
    'site_name', 'tagline', 'email', 'phone', 'whatsapp', 'address', 'city',
    'google_maps_url', 'google_maps_embed', 'facebook', 'instagram', 'youtube', 'tiktok', 'opening_hours',
    'primary_color', 'secondary_color', 'footer_text', 'copyright',
    'minimum_advance_percent', 'pending_booking_expiry_hours', 'cancellation_policy',
    'ga_id', 'gtm_id', 'gsc_verification', 'bing_verification',
];
$boolFields = [
    'allow_online_booking', 'require_admin_confirmation', 'show_public_availability',
    'allow_hall_select', 'allow_timeslot_select', 'enable_advance_payment', 'enable_payment_tracking', 'hold_pending_slots',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    foreach ($textFields as $field) {
        wh_set_setting($field, wh_input_post($field), $businessId);
    }
    foreach ($boolFields as $field) {
        wh_set_setting($field, isset($_POST[$field]) ? '1' : '0', $businessId);
    }
    $logo = wh_handle_image_upload('logo', 'logos', 'logo');
    if ($logo) wh_set_setting('logo', $logo, $businessId);
    $favicon = wh_handle_image_upload('favicon', 'logos', 'favicon');
    if ($favicon) wh_set_setting('favicon', $favicon, $businessId);

    wh_flash_set('success', 'Settings saved.');
    wh_redirect(BASE_URL . '/admin/settings.php');
}

$s = wh_get_settings($businessId);
$pageTitle = 'Settings';
$activePage = 'settings';
require __DIR__ . '/header.php';
?>
<form method="post" enctype="multipart/form-data">
  <?= wh_csrf_field() ?>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Site Identity</h3>
    <div class="form-grid">
      <div class="form-group"><label>Website Name</label><input type="text" name="site_name" value="<?= e($s['site_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Tagline</label><input type="text" name="tagline" value="<?= e($s['tagline'] ?? '') ?>"></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Logo</label><input type="file" name="logo" accept="image/*"><?php if (!empty($s['logo'])): ?><br><img src="<?= e(BASE_URL . '/' . $s['logo']) ?>" style="height:40px;margin-top:6px;"><?php endif; ?></div>
      <div class="form-group"><label>Favicon</label><input type="file" name="favicon" accept="image/*"><?php if (!empty($s['favicon'])): ?><br><img src="<?= e(BASE_URL . '/' . $s['favicon']) ?>" style="height:32px;margin-top:6px;"><?php endif; ?></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Primary Color</label><input type="color" name="primary_color" value="<?= e($s['primary_color'] ?? '#7a1f3d') ?>"></div>
      <div class="form-group"><label>Secondary Color</label><input type="color" name="secondary_color" value="<?= e($s['secondary_color'] ?? '#c79a4b') ?>"></div>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Contact Details</h3>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($s['email'] ?? '') ?>"></div>
      <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($s['phone'] ?? '') ?>"></div>
      <div class="form-group"><label>WhatsApp</label><input type="text" name="whatsapp" value="<?= e($s['whatsapp'] ?? '') ?>"></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= e($s['address'] ?? '') ?>"></div>
      <div class="form-group"><label>City</label><input type="text" name="city" value="<?= e($s['city'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Opening Hours</label><input type="text" name="opening_hours" value="<?= e($s['opening_hours'] ?? '') ?>"></div>
    <div class="form-group"><label>Google Maps URL</label><input type="text" name="google_maps_url" value="<?= e($s['google_maps_url'] ?? '') ?>"></div>
    <div class="form-group"><label>Google Maps Embed (iframe code)</label><textarea name="google_maps_embed" rows="2"><?= e($s['google_maps_embed'] ?? '') ?></textarea></div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Social Links</h3>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Facebook</label><input type="text" name="facebook" value="<?= e($s['facebook'] ?? '') ?>"></div>
      <div class="form-group"><label>Instagram</label><input type="text" name="instagram" value="<?= e($s['instagram'] ?? '') ?>"></div>
      <div class="form-group"><label>YouTube</label><input type="text" name="youtube" value="<?= e($s['youtube'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>TikTok</label><input type="text" name="tiktok" value="<?= e($s['tiktok'] ?? '') ?>"></div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Booking Settings</h3>
    <p class="hint">"Public Availability" ON karne se website visitors ko booked/available dates nazar aayengi.</p>
    <div class="form-grid cols-3">
      <label><input type="checkbox" name="allow_online_booking" value="1" style="width:auto;" <?= wh_setting_bool('allow_online_booking', true, $businessId) ? 'checked' : '' ?>> Allow Online Booking</label>
      <label><input type="checkbox" name="require_admin_confirmation" value="1" style="width:auto;" <?= wh_setting_bool('require_admin_confirmation', true, $businessId) ? 'checked' : '' ?>> Require Admin Confirmation</label>
      <label><input type="checkbox" name="show_public_availability" value="1" style="width:auto;" <?= wh_setting_bool('show_public_availability', true, $businessId) ? 'checked' : '' ?>> Show Public Availability</label>
      <label><input type="checkbox" name="allow_hall_select" value="1" style="width:auto;" <?= wh_setting_bool('allow_hall_select', true, $businessId) ? 'checked' : '' ?>> Customer Can Select Hall</label>
      <label><input type="checkbox" name="allow_timeslot_select" value="1" style="width:auto;" <?= wh_setting_bool('allow_timeslot_select', true, $businessId) ? 'checked' : '' ?>> Customer Can Select Time Slot</label>
      <label><input type="checkbox" name="hold_pending_slots" value="1" style="width:auto;" <?= wh_setting_bool('hold_pending_slots', true, $businessId) ? 'checked' : '' ?>> Hold Slot While Pending</label>
    </div>
    <div class="form-group"><label>Pending Booking Expiry (hours)</label><input type="number" name="pending_booking_expiry_hours" value="<?= e($s['pending_booking_expiry_hours'] ?? 48) ?>"></div>
    <div class="form-group"><label>Cancellation Policy</label><textarea name="cancellation_policy" rows="2"><?= e($s['cancellation_policy'] ?? '') ?></textarea></div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Payment Settings</h3>
    <div class="form-grid cols-3">
      <label><input type="checkbox" name="enable_advance_payment" value="1" style="width:auto;" <?= wh_setting_bool('enable_advance_payment', true, $businessId) ? 'checked' : '' ?>> Enable Advance Payment</label>
      <label><input type="checkbox" name="enable_payment_tracking" value="1" style="width:auto;" <?= wh_setting_bool('enable_payment_tracking', true, $businessId) ? 'checked' : '' ?>> Enable Payment Tracking</label>
      <div class="form-group"><label>Minimum Advance (%)</label><input type="number" name="minimum_advance_percent" value="<?= e($s['minimum_advance_percent'] ?? 20) ?>"></div>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Footer</h3>
    <div class="form-group"><label>Footer Text</label><input type="text" name="footer_text" value="<?= e($s['footer_text'] ?? '') ?>"></div>
    <div class="form-group"><label>Copyright</label><input type="text" name="copyright" value="<?= e($s['copyright'] ?? '') ?>"></div>
  </div>

  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Analytics</h3>
    <div class="form-grid">
      <div class="form-group"><label>Google Analytics ID</label><input type="text" name="ga_id" value="<?= e($s['ga_id'] ?? '') ?>"></div>
      <div class="form-group"><label>Google Tag Manager ID</label><input type="text" name="gtm_id" value="<?= e($s['gtm_id'] ?? '') ?>"></div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Google Search Console Verification</label><input type="text" name="gsc_verification" value="<?= e($s['gsc_verification'] ?? '') ?>"></div>
      <div class="form-group"><label>Bing Verification</label><input type="text" name="bing_verification" value="<?= e($s['bing_verification'] ?? '') ?>"></div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Save All Settings</button>
</form>
<?php require __DIR__ . '/footer.php'; ?>
