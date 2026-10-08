<?php
require __DIR__ . '/includes/admin.php';
require_section('settings');

$keys = ['site_name', 'tagline', 'logo_height', 'phone', 'whatsapp', 'email', 'order_email', 'address', 'business_hours', 'map_embed',
    'facebook', 'instagram', 'tiktok', 'youtube', 'pinterest', 'announcement_enabled', 'announcement', 'footer_about', 'copyright',
    'currency', 'color_gold', 'color_black', 'color_cream', 'preloader', 'guest_checkout', 'order_prefix', 'low_stock', 'emails_enabled', 'mail_from'];

if (is_post()) {
    require_csrf();
    save_posted_settings($keys);
    $logo = handle_image('logo', setting('logo') ?: null, 'settings', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    $fav = handle_image('favicon', setting('favicon') ?: null, 'settings', ['png', 'ico', 'jpg', 'jpeg', 'webp', 'gif']);
    save_settings(['logo' => (string)$logo, 'favicon' => (string)$fav]);
    flash('success', 'Settings saved.');
    redirect('admin/settings#' . post('tab', 'general'));
}

$s = fn($k, $d = '') => setting($k, $d);
admin_header('Site Settings', 'settings');
?>
<form method="post" enctype="multipart/form-data" class="tabs-wrap" data-admin-tabs>
  <?= csrf_field() ?><input type="hidden" name="tab" value="general" data-tab-input>
  <nav class="atabs">
    <a href="#general" class="active">General & Branding</a><a href="#contact">Contact & Social</a><a href="#header">Header & Footer</a><a href="#store">Store Options</a><a href="#theme">Theme Colours</a>
  </nav>

  <section class="card atab active" id="general">
    <div class="row-2"><?= f_text('site_name', 'Store name', $s('site_name')) ?><?= f_text('tagline', 'Tagline', $s('tagline'), ['help' => 'Shown under the text logo']) ?></div>
    <div class="row-2">
      <?= f_image('logo', 'Logo', $s('logo') ?: null, 'PNG with transparent background works best. Leave empty to use the elegant text logo.') ?>
      <?= f_image('favicon', 'Site icon (favicon)', $s('favicon') ?: null, 'Square PNG/ICO, 64×64 or larger.') ?>
    </div>
    <?= f_text('logo_height', 'Logo height (px)', $s('logo_height', 46), ['type' => 'number', 'attrs' => 'min="20" max="140"']) ?>
  </section>

  <section class="card atab" id="contact">
    <div class="row-2"><?= f_text('phone', 'Phone number', $s('phone')) ?><?= f_text('whatsapp', 'WhatsApp number', $s('whatsapp'), ['help' => 'International format without + e.g. 923001234567 — shows a floating WhatsApp button']) ?></div>
    <div class="row-2"><?= f_text('email', 'Store email', $s('email'), ['type' => 'email']) ?><?= f_text('order_email', 'Send new-order alerts to', $s('order_email'), ['type' => 'email', 'help' => 'Defaults to the store email']) ?></div>
    <?= f_text('address', 'Address', $s('address')) ?>
    <?= f_text('business_hours', 'Business hours', $s('business_hours')) ?>
    <?= f_text('map_embed', 'Google Map embed code (optional)', $s('map_embed'), ['type' => 'textarea', 'rows' => 3, 'help' => 'Google Maps → Share → Embed a map → copy HTML. Shown on the Contact page.']) ?>
    <div class="row-2"><?= f_text('facebook', 'Facebook URL', $s('facebook')) ?><?= f_text('instagram', 'Instagram URL', $s('instagram')) ?></div>
    <div class="row-3"><?= f_text('tiktok', 'TikTok URL', $s('tiktok')) ?><?= f_text('youtube', 'YouTube URL', $s('youtube')) ?><?= f_text('pinterest', 'Pinterest URL', $s('pinterest')) ?></div>
  </section>

  <section class="card atab" id="header">
    <?= f_switch('announcement_enabled', 'Show announcement bar', $s('announcement_enabled', '1') === '1') ?>
    <?= f_text('announcement', 'Announcement text (scrolling)', $s('announcement')) ?>
    <?= f_switch('preloader', 'Show animated preloader', $s('preloader', '1') === '1') ?>
    <?= f_text('footer_about', 'Footer about text', $s('footer_about'), ['type' => 'textarea', 'rows' => 3]) ?>
    <?= f_text('copyright', 'Copyright line', $s('copyright'), ['help' => 'Use {year} for the current year']) ?>
  </section>

  <section class="card atab" id="store">
    <div class="row-3">
      <?= f_text('currency', 'Currency symbol', $s('currency', 'Rs.')) ?>
      <?= f_text('order_prefix', 'Order number prefix', $s('order_prefix', 'EM')) ?>
      <?= f_text('low_stock', 'Low-stock alert at', $s('low_stock', 3), ['type' => 'number']) ?>
    </div>
    <?= f_switch('guest_checkout', 'Allow guest checkout', $s('guest_checkout', '1') === '1', 'When off, customers must log in or register before checkout.') ?>
    <?= f_switch('emails_enabled', 'Send order e-mails (customer + admin)', $s('emails_enabled', '1') === '1', 'Uses your hosting mail server (PHP mail).') ?>
    <?= f_text('mail_from', 'Send e-mails from', $s('mail_from'), ['type' => 'email', 'help' => 'Use an address on your own domain, e.g. orders@yourdomain.com']) ?>
  </section>

  <section class="card atab" id="theme">
    <div class="row-3">
      <label class="field"><span>Gold</span><input type="color" name="color_gold" value="<?= e($s('color_gold', '#c9a24a')) ?>"></label>
      <label class="field"><span>Black</span><input type="color" name="color_black" value="<?= e($s('color_black', '#0b0b0b')) ?>"></label>
      <label class="field"><span>Cream background</span><input type="color" name="color_cream" value="<?= e($s('color_cream', '#f7f1e6')) ?>"></label>
    </div>
    <p class="muted sm">These colours are applied across buttons, accents and backgrounds of the storefront.</p>
  </section>

  <div class="save-bar"><button class="btn btn-primary">Save Settings</button></div>
</form>
<?php admin_footer();
