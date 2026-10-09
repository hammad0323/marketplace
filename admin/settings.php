<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('settings.general');

$text = [
    'site_name' => 120, 'brand_name' => 120, 'site_tagline' => 120, 'browser_title' => 190, 'contact_phone' => 40, 'whatsapp_number' => 20,
    'support_email' => 190, 'notification_email' => 190, 'business_hours' => 120, 'currency_code' => 3, 'currency_symbol' => 8,
    'date_format' => 20, 'time_format' => 20, 'default_language' => 10, 'order_prefix' => 6, 'copyright_text' => 190, 'store_country' => 80,
    'popular_searches' => 255, 'mega_menu_caption' => 80, 'mega_menu_link' => 255, 'pdp_delivery_text' => 190, 'pdp_returns_text' => 190,
    'whatsapp_message' => 190, 'contact_intro' => 255, 'shop_heading' => 120, 'maintenance_message' => 255,
];
$long = ['business_address' => 500, 'footer_about' => 500, 'pdp_shipping_returns' => 3000, 'shop_intro' => 1000];
$bools = ['maintenance_mode', 'wishlist_enabled', 'reviews_enabled', 'recently_viewed_enabled', 'email_enabled', 'email_status_updates', 'newsletter_welcome_email', 'low_stock_email', 'checkout_require_terms', 'show_sample_content', 'whatsapp_float'];
$socials = ['instagram', 'facebook', 'tiktok', 'youtube', 'pinterest', 'x'];
$errors = [];

if (is_post()) {
    require_csrf();
    $in = [];
    foreach ($text as $k => $max) {
        $in[$k] = mb_substr(input($k), 0, $max);
    }
    foreach ($long as $k => $max) {
        $in[$k] = mb_substr(input($k), 0, $max);
    }
    foreach (['support_email', 'notification_email'] as $k) {
        if ($in[$k] !== '' && !valid_email($in[$k])) {
            $errors[] = 'Invalid email: ' . $k;
        }
    }
    $in['whatsapp_number'] = preg_replace('/[^0-9]/', '', $in['whatsapp_number']);
    $in['currency_code'] = strtoupper(preg_replace('/[^A-Za-z]/', '', $in['currency_code'])) ?: 'PKR';
    $in['order_prefix'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $in['order_prefix'])) ?: 'BG';
    $tz = input('timezone');
    $in['timezone'] = in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Asia/Karachi';
    $in['currency_position'] = input('currency_position') === 'after' ? 'after' : 'before';
    $in['currency_decimals'] = (string) max(0, min(2, input_int('currency_decimals')));
    $in['products_per_page'] = (string) max(4, min(60, input_int('products_per_page', 12)));
    $in['unpaid_order_timeout_hours'] = (string) max(1, min(72, input_int('unpaid_order_timeout_hours', 2)));
    foreach ($socials as $s) {
        $u = input('social_' . $s);
        if ($u !== '' && !preg_match('#^https://#i', $u)) {
            $errors[] = ucfirst($s) . ' URL must start with https://';
        }
        $in['social_' . $s] = mb_substr($u, 0, 255);
    }
    foreach ($bools as $b) {
        $in[$b] = input_bool($b) ? '1' : '0';
    }
    // Navigation menu
    $menu = [];
    foreach (input_array('menu') as $m) {
        if (is_array($m) && trim($m['label'] ?? '') !== '') {
            $link = trim($m['url'] ?? '');
            if ($link !== '' && safe_link($link, '') === '') {
                $errors[] = 'Menu link "' . $link . '" must be a site path (/shop) or https:// URL.';
            }
            $menu[] = ['label' => mb_substr(trim($m['label']), 0, 40), 'url' => mb_substr($link, 0, 255), 'mega' => !empty($m['mega'])];
        }
    }
    [$logo, $e1] = handle_image_field('logo', setting('logo_path') ?: null, 'brand');
    [$favicon, $e2] = handle_image_field('favicon', strpos((string) setting('favicon_path'), 'uploads/') === 0 ? setting('favicon_path') : null, 'brand');
    [$mega, $e3] = handle_image_field('mega_menu_image', setting('mega_menu_image') ?: null, 'brand');
    foreach (array_filter([$e1, $e2, $e3]) as $er) {
        $errors[] = $er;
    }
    if (!$errors) {
        foreach ($in as $k => $v) {
            save_setting($k, $v, 'general');
        }
        save_setting('main_menu', $menu, 'general');
        save_setting('logo_path', (string) $logo, 'general');
        if ($favicon !== null) {
            save_setting('favicon_path', $favicon ?: 'assets/img/brand/favicon.svg', 'general');
        }
        save_setting('mega_menu_image', (string) $mega, 'general');
        audit_log('settings_updated', 'settings', null, ['keys' => 'general']);
        flash('success', 'Settings saved.');
        redirect(admin_url('settings'));
    }
}
$v = fn($k, $d = '') => is_post() ? input($k, $d) : setting($k, $d);
$menu = setting_json('main_menu', []);
admin_header('General settings', 'settings');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-xl-6">
    <div class="card mb-3"><div class="card-header">Store identity</div><div class="card-body"><div class="row">
      <div class="col-md-6"><?= f_text('site_name', 'Website name', $v('site_name')) ?></div><div class="col-md-6"><?= f_text('brand_name', 'Brand name', $v('brand_name')) ?></div>
      <div class="col-md-6"><?= f_text('site_tagline', 'Tagline', $v('site_tagline')) ?></div><div class="col-md-6"><?= f_text('browser_title', 'Homepage browser title', $v('browser_title')) ?></div>
      <div class="col-md-6"><?= f_image('logo', 'Logo (blank = text wordmark)', setting('logo_path') ?: null, 'Light logo for the dark header. PNG/WebP, ~300×80.') ?></div>
      <div class="col-md-6"><?= f_image('favicon', 'Site icon / favicon', setting('favicon_path') ?: null, 'Square PNG, 512×512.') ?></div>
      <div class="col-12"><?= f_textarea('footer_about', 'Footer description', $v('footer_about'), ['rows' => 2]) ?></div>
      <div class="col-12"><?= f_text('copyright_text', 'Copyright text', $v('copyright_text')) ?></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header">Contact</div><div class="card-body"><div class="row">
      <div class="col-md-6"><?= f_text('contact_phone', 'Phone', $v('contact_phone')) ?></div><div class="col-md-6"><?= f_text('whatsapp_number', 'WhatsApp number (international, digits)', $v('whatsapp_number'), [], 'e.g. 923001234567') ?></div>
      <div class="col-md-6"><?= f_text('support_email', 'Support email (public)', $v('support_email')) ?></div><div class="col-md-6"><?= f_text('notification_email', 'Order notification email (admin)', $v('notification_email')) ?></div>
      <div class="col-md-6"><?= f_textarea('business_address', 'Business address', $v('business_address'), ['rows' => 2]) ?></div><div class="col-md-6"><?= f_text('business_hours', 'Business hours', $v('business_hours')) ?><?= f_check('whatsapp_float', 'Floating WhatsApp button', setting_bool('whatsapp_float')) ?></div>
      <div class="col-md-6"><?= f_text('whatsapp_message', 'WhatsApp pre-filled message', $v('whatsapp_message')) ?></div><div class="col-md-6"><?= f_text('contact_intro', 'Contact page intro', $v('contact_intro')) ?></div>
      <?php foreach ($socials as $s): ?><div class="col-md-6"><?= f_text('social_' . $s, ucfirst($s) . ' URL', $v('social_' . $s)) ?></div><?php endforeach; ?>
    </div></div></div>
  </div>
  <div class="col-xl-6">
    <div class="card mb-3"><div class="card-header">Regional</div><div class="card-body"><div class="row">
      <div class="col-md-4"><?= f_text('currency_code', 'Currency code', $v('currency_code')) ?></div><div class="col-md-4"><?= f_text('currency_symbol', 'Symbol', $v('currency_symbol')) ?></div>
      <div class="col-md-4"><?= f_select('currency_position', 'Symbol position', ['before' => 'Before (Rs. 1,000)', 'after' => 'After (1,000 Rs.)'], $v('currency_position')) ?></div>
      <div class="col-md-4"><?= f_select('currency_decimals', 'Decimals', ['0' => '0', '2' => '2'], $v('currency_decimals')) ?></div>
      <div class="col-md-8"><?= f_select('timezone', 'Time zone', array_combine(timezone_identifiers_list(), timezone_identifiers_list()), $v('timezone', 'Asia/Karachi')) ?></div>
      <div class="col-md-4"><?= f_text('date_format', 'Date format', $v('date_format'), [], 'PHP format, e.g. d M Y') ?></div><div class="col-md-4"><?= f_text('time_format', 'Time format', $v('time_format')) ?></div>
      <div class="col-md-4"><?= f_text('default_language', 'Language code', $v('default_language')) ?></div>
      <div class="col-md-6"><?= f_text('store_country', 'Store country', $v('store_country')) ?></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header">Navigation menu</div><div class="card-body">
      <div id="menuRows" data-max="8"><div class="repeater-rows">
        <?php foreach ($menu as $i => $m): ?>
          <div class="repeater-row d-flex gap-2 align-items-center"><input class="form-control form-control-sm" name="menu[<?= $i ?>][label]" value="<?= e($m['label']) ?>" placeholder="Label"><input class="form-control form-control-sm" name="menu[<?= $i ?>][url]" value="<?= e($m['url']) ?>" placeholder="/shop"><label class="form-check small text-nowrap mb-0"><input class="form-check-input" type="checkbox" name="menu[<?= $i ?>][mega]" value="1" <?= !empty($m['mega']) ? 'checked' : '' ?>> Mega menu</label><button type="button" class="btn btn-sm btn-light" data-repeater-remove><i class="bi bi-x"></i></button></div>
        <?php endforeach; ?></div>
        <template><div class="repeater-row d-flex gap-2 align-items-center"><input class="form-control form-control-sm" name="menu[__i__][label]" placeholder="Label"><input class="form-control form-control-sm" name="menu[__i__][url]" placeholder="/shop"><label class="form-check small text-nowrap mb-0"><input class="form-check-input" type="checkbox" name="menu[__i__][mega]" value="1"> Mega menu</label><button type="button" class="btn btn-sm btn-light" data-repeater-remove><i class="bi bi-x"></i></button></div></template>
      </div>
      <button type="button" class="btn btn-sm btn-light mb-3" data-repeater-add="#menuRows"><i class="bi bi-plus"></i> Add menu item</button>
      <p class="small text-muted">A "mega menu" item shows all active categories in a dropdown.</p>
      <div class="row"><div class="col-md-6"><?= f_image('mega_menu_image', 'Mega menu feature image', setting('mega_menu_image') ?: null) ?></div><div class="col-md-6"><?= f_text('mega_menu_caption', 'Feature caption', $v('mega_menu_caption')) ?><?= f_text('mega_menu_link', 'Feature link', $v('mega_menu_link')) ?></div></div>
      <?= f_text('popular_searches', 'Popular searches (comma separated)', $v('popular_searches')) ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">Store features</div><div class="card-body"><div class="row">
      <div class="col-md-6"><?= f_check('wishlist_enabled', 'Wishlist', setting_bool('wishlist_enabled', true)) ?><?= f_check('reviews_enabled', 'Product reviews (moderated)', setting_bool('reviews_enabled', true)) ?><?= f_check('recently_viewed_enabled', 'Recently viewed products', setting_bool('recently_viewed_enabled', true)) ?><?= f_check('checkout_require_terms', 'Require terms acceptance at checkout', setting_bool('checkout_require_terms', true)) ?></div>
      <div class="col-md-6"><?= f_check('email_enabled', 'Send emails', setting_bool('email_enabled', true)) ?><?= f_check('email_status_updates', 'Email customers on status changes', setting_bool('email_status_updates', true)) ?><?= f_check('newsletter_welcome_email', 'Newsletter welcome email', setting_bool('newsletter_welcome_email', true)) ?><?= f_check('low_stock_email', 'Low-stock email alerts', setting_bool('low_stock_email', true)) ?></div>
      <div class="col-md-4"><?= f_text('order_prefix', 'Order number prefix', $v('order_prefix')) ?></div><div class="col-md-4"><?= f_number('products_per_page', 'Products per page', $v('products_per_page'), ['min' => 4, 'max' => 60, 'step' => 1]) ?></div>
      <div class="col-md-4"><?= f_number('unpaid_order_timeout_hours', 'Release unpaid online orders after (h)', $v('unpaid_order_timeout_hours'), ['min' => 1, 'max' => 72, 'step' => 1]) ?></div>
      <div class="col-md-6"><?= f_text('shop_heading', 'Shop page heading', $v('shop_heading')) ?></div><div class="col-md-6"><?= f_textarea('shop_intro', 'Shop page intro', $v('shop_intro'), ['rows' => 2]) ?></div>
      <div class="col-md-6"><?= f_text('pdp_delivery_text', 'Product page: delivery line', $v('pdp_delivery_text')) ?></div><div class="col-md-6"><?= f_text('pdp_returns_text', 'Product page: returns line', $v('pdp_returns_text')) ?></div>
      <div class="col-12"><?= f_textarea('pdp_shipping_returns', 'Product page: "Shipping & returns" panel', $v('pdp_shipping_returns'), ['rows' => 3]) ?></div>
    </div></div></div>
    <div class="card"><div class="card-header">Launch</div><div class="card-body">
      <?= f_check('maintenance_mode', 'Maintenance mode (storefront shows "back soon"; admins can still browse)', setting_bool('maintenance_mode')) ?>
      <?= f_text('maintenance_message', 'Maintenance message', $v('maintenance_message')) ?>
      <?= f_check('show_sample_content', 'Show sample content (demo testimonials) — turn OFF before launch', setting_bool('show_sample_content')) ?>
    </div></div>
  </div>
</div>
<div class="sticky-actions"><?= f_submit('Save settings') ?></div>
</form>
<?php admin_footer();
