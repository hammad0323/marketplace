<?php
/** Store settings: brand, contact, social, announcement, currency, orders & reviews, email. */
require __DIR__ . '/../includes/init.php';
admin_require('settings');
if (is_post()) {
    if (input('action', '', 'post') === 'test_mail') {
        $to = current_admin()['email'];
        $ok = send_mail($to, 'Test email from ' . store_name(), mail_layout('Email is working', '<p>This test confirms your store can send email.</p>'));
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to . '.' : 'Sending failed — check the settings and logs/mail.log.');
        redirect('admin/settings/');
    }
    $text = [
        'general' => ['store_name' => 120, 'store_tagline' => 190, 'store_email' => 190, 'store_phone' => 40, 'store_whatsapp' => 40, 'store_address' => 500, 'store_hours' => 120,
                      'currency_symbol' => 10, 'currency_code' => 3, 'copyright_text' => 255, 'footer_about' => 500],
        'announcement' => ['announcement_items' => 1000, 'announcement_link' => 255],
        'social' => ['social_instagram' => 255, 'social_facebook' => 255, 'social_tiktok' => 255, 'social_youtube' => 255],
        'orders' => ['order_prefix' => 6, 'unpaid_order_timeout_hours' => 4, 'order_notify_email' => 500, 'new_arrival_days' => 4, 'products_per_page' => 3, 'returns_note' => 500],
        'mail' => ['mail_from_email' => 190, 'mail_from_name' => 120, 'smtp_host' => 190, 'smtp_port' => 5, 'smtp_user' => 190],
    ];
    foreach ($text as $group => $keys) {
        foreach ($keys as $k => $max) {
            setting_save($k, mb_substr(strip_tags(str_replace("\r", '', (string) ($_POST[$k] ?? ''))), 0, $max), $group);
        }
    }
    foreach (['social_instagram', 'social_facebook', 'social_tiktok', 'social_youtube'] as $k) {
        if (setting($k) && !preg_match('#^https://#i', setting($k))) { setting_save($k, '', 'social'); flash('warning', 'Social links must start with https:// — ' . $k . ' was cleared.'); }
    }
    setting_save('currency_position', input('currency_position', '', 'post') === 'after' ? 'after' : 'before', 'general');
    setting_save('currency_decimals', (string) max(0, min(2, input_int('currency_decimals'))), 'general');
    setting_save('mail_driver', input('mail_driver', '', 'post') === 'mail' ? 'mail' : 'smtp', 'mail');
    setting_save('smtp_secure', in_array(input('smtp_secure', '', 'post'), ['ssl', 'tls', 'none'], true) ? input('smtp_secure', '', 'post') : 'ssl', 'mail');
    if (($pw = (string) ($_POST['smtp_pass'] ?? '')) !== '') setting_save('smtp_pass_enc', encrypt_secret($pw), 'mail');
    foreach (['show_brand_text' => 'general', 'announcement_enabled' => 'announcement', 'guest_checkout' => 'orders', 'reviews_auto_approve' => 'orders', 'reviews_require_purchase' => 'orders', 'mail_enabled' => 'mail'] as $k => $g) {
        setting_save($k, (string) input_bool($k), $g);
    }
    foreach (['logo', 'favicon'] as $k) {
        $v = a_try(fn() => a_image_value($k, setting($k)), setting($k));
        setting_save($k, (string) ($v ?: ($k === 'logo' ? 'assets/images/brand/logo-round.webp' : 'assets/images/brand/favicon.png')), 'general');
    }
    audit_log('store_settings', 'settings');
    flash('success', 'Settings saved.');
    redirect('admin/settings/');
}
$s = fn($k, $d = '') => setting($k, $d);
admin_header('Store settings');
?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<ul class="nav a-tabs"><?php foreach (['brand' => 'Brand & contact', 'announce' => 'Announcement', 'social' => 'Social', 'orders' => 'Orders & reviews', 'mail' => 'Email'] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $k === 'brand' ? ' active' : '' ?>" data-bs-toggle="tab" href="#t-<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="t-brand"><div class="row g-4"><div class="col-lg-6"><div class="card a-card"><div class="card-body">
    <?= a_input('store_name', 'Store name', $s('store_name')) ?>
    <?= a_input('store_tagline', 'Tagline', $s('store_tagline')) ?>
    <?= a_image('logo', 'Logo (square / round works best)', $s('logo')) ?>
    <?= a_check('show_brand_text', 'Show “Urban Collections” wordmark beside the logo', setting_bool('show_brand_text', true)) ?>
    <?= a_image('favicon', 'Favicon (64×64 PNG)', $s('favicon')) ?>
    <?= a_textarea('footer_about', 'Footer description', $s('footer_about'), 2) ?>
    <?= a_input('copyright_text', 'Copyright line ({year} = current year)', $s('copyright_text')) ?>
</div></div></div><div class="col-lg-6"><div class="card a-card"><div class="card-body">
    <?= a_input('store_email', 'Customer service email', $s('store_email'), 'email') ?>
    <?= a_input('store_phone', 'Phone', $s('store_phone')) ?>
    <?= a_input('store_whatsapp', 'WhatsApp number (shows floating chat button, e.g. 923001234567)', $s('store_whatsapp')) ?>
    <?= a_textarea('store_address', 'Address', $s('store_address'), 2) ?>
    <?= a_input('store_hours', 'Opening hours', $s('store_hours')) ?>
    <div class="row"><div class="col-4"><?= a_input('currency_symbol', 'Currency symbol', $s('currency_symbol', 'Rs.')) ?></div><div class="col-4"><?= a_input('currency_code', 'ISO code', $s('currency_code', 'PKR')) ?></div>
    <div class="col-4"><?= a_select('currency_position', 'Symbol', ['before' => 'Before', 'after' => 'After'], $s('currency_position', 'before')) ?></div><div class="col-4"><?= a_input('currency_decimals', 'Decimals', $s('currency_decimals', 0), 'number', ['min' => 0, 'max' => 2]) ?></div></div>
</div></div></div></div></div>
<div class="tab-pane fade" id="t-announce"><div class="card a-card"><div class="card-body">
    <?= a_check('announcement_enabled', 'Show announcement bar', setting_bool('announcement_enabled')) ?>
    <?= a_textarea('announcement_items', 'Messages (one per line — they rotate)', $s('announcement_items'), 4) ?>
    <?= a_input('announcement_link', 'Link (optional)', $s('announcement_link'), 'text', ['placeholder' => 'shipping-policy']) ?>
    <p class="small text-muted">Bar colours are set in <a href="<?= e(url('admin/settings/theme.php')) ?>">Theme &amp; Design</a>.</p>
</div></div></div>
<div class="tab-pane fade" id="t-social"><div class="card a-card"><div class="card-body">
    <?= a_input('social_instagram', 'Instagram URL', $s('social_instagram'), 'url') ?>
    <?= a_input('social_facebook', 'Facebook URL', $s('social_facebook'), 'url') ?>
    <?= a_input('social_tiktok', 'TikTok URL', $s('social_tiktok'), 'url') ?>
    <?= a_input('social_youtube', 'YouTube URL', $s('social_youtube'), 'url') ?>
</div></div></div>
<div class="tab-pane fade" id="t-orders"><div class="card a-card"><div class="card-body"><div class="row">
    <div class="col-md-4"><?= a_input('order_prefix', 'Order number prefix', $s('order_prefix', 'UC')) ?></div>
    <div class="col-md-4"><?= a_input('unpaid_order_timeout_hours', 'Cancel unpaid online orders after (hours, 0 = never)', $s('unpaid_order_timeout_hours', 48), 'number', ['min' => 0]) ?></div>
    <div class="col-md-4"><?= a_input('order_notify_email', 'New-order notification emails (comma separated)', $s('order_notify_email')) ?></div>
    <div class="col-md-4"><?= a_input('new_arrival_days', '“New arrival” window (days)', $s('new_arrival_days', 45), 'number') ?></div>
    <div class="col-md-4"><?= a_input('products_per_page', 'Products per page', $s('products_per_page', 24), 'number', ['min' => 8, 'max' => 60]) ?></div>
    <div class="col-md-4"><?= a_input('returns_note', 'Returns note (product pages)', $s('returns_note')) ?></div>
    <div class="col-md-4"><?= a_check('guest_checkout', 'Allow guest checkout', setting_bool('guest_checkout', true)) ?></div>
    <div class="col-md-4"><?= a_check('reviews_auto_approve', 'Publish reviews without moderation', setting_bool('reviews_auto_approve')) ?></div>
    <div class="col-md-4"><?= a_check('reviews_require_purchase', 'Only buyers can review', setting_bool('reviews_require_purchase')) ?></div>
</div></div></div></div>
<div class="tab-pane fade" id="t-mail"><div class="card a-card"><div class="card-body"><div class="row">
    <div class="col-12"><?= a_check('mail_enabled', 'Send emails (order confirmations, status updates, password resets)', setting_bool('mail_enabled')) ?></div>
    <div class="col-md-6"><?= a_input('mail_from_email', 'From email (a mailbox on your domain)', $s('mail_from_email'), 'email') ?></div>
    <div class="col-md-6"><?= a_input('mail_from_name', 'From name', $s('mail_from_name', store_name())) ?></div>
    <div class="col-md-4"><?= a_select('mail_driver', 'Method', ['smtp' => 'SMTP (recommended)', 'mail' => 'PHP mail()'], $s('mail_driver', 'smtp')) ?></div>
    <div class="col-md-4"><?= a_input('smtp_host', 'SMTP host (e.g. mail.yourdomain.com)', $s('smtp_host')) ?></div>
    <div class="col-md-2"><?= a_input('smtp_port', 'Port', $s('smtp_port', 465), 'number') ?></div>
    <div class="col-md-2"><?= a_select('smtp_secure', 'Security', ['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'], $s('smtp_secure', 'ssl')) ?></div>
    <div class="col-md-6"><?= a_input('smtp_user', 'SMTP username', $s('smtp_user')) ?></div>
    <div class="col-md-6"><?= a_input('smtp_pass', 'SMTP password', '', 'password', ['placeholder' => setting('smtp_pass_enc') ? '•••••••• saved (leave blank to keep)' : '', 'autocomplete' => 'new-password'], 'Stored encrypted.') ?></div>
</div></div></div>
<button class="btn btn-outline-dark" form="testMail">Send test email to me</button></div>
</div>
<?= a_save_bar() ?>
</form>
<form id="testMail" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail"></form>
<?php admin_footer();
