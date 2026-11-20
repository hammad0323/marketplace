<?php
/** Platform settings: subscription price and rules, file retention, app links, privacy notice. */
require __DIR__ . '/_init.php';
require_super_admin();
$fields = [
    'platform_name' => ['Platform name', 'text'], 'platform_tagline' => ['Tagline', 'text'], 'support_phone' => ['Support phone / WhatsApp', 'text'], 'support_email' => ['Support email', 'email'],
    'default_subscription_price' => ['Default monthly subscription (PKR)', 'number'], 'default_grace_days' => ['Grace period after expiry (days)', 'number'],
    'reminder_days_before' => ['Send expiry reminder (days before)', 'number'], 'default_retention_days' => ['Default file retention for new shops (days)', 'number'],
    'max_retention_days' => ['Maximum retention any shop may set (days)', 'number'], 'max_upload_mb' => ['Maximum upload size (MB)', 'number'], 'temp_upload_hours' => ['Delete unused uploads after (hours)', 'number'],
    'android_package' => ['Android package name', 'text'], 'android_sha256' => ['Android signing SHA-256 fingerprints (comma separated)', 'text'],
    'ios_app_id' => ['iOS app ID (TEAMID.bundle.id)', 'text'], 'play_store_url' => ['Google Play link', 'url'], 'app_store_url' => ['App Store link', 'url'],
];
if (is_post()) {
    $old = setting('default_subscription_price');
    foreach ($fields as $k => [$label, $type]) {
        $v = trim((string) ($_POST[$k] ?? ''));
        if ($type === 'number') {
            $v = (string) max(0, $k === 'default_subscription_price' ? round((float) $v, 2) : (int) $v);
        }
        setting_save($k, mb_substr($v, 0, 1000));
    }
    setting_save('max_upload_mb', (string) max(1, min(100, (int) setting('max_upload_mb'))));
    setting_save('max_retention_days', (string) max(1, min(365, (int) setting('max_retention_days'))));
    setting_save('show_install_prompt', !empty($_POST['show_install_prompt']) ? '1' : '0');
    setting_save('privacy_notice', mb_substr(trim((string) ($_POST['privacy_notice'] ?? '')), 0, 3000));
    activity('admin', (int) current_admin()['id'], 'settings_saved', null, null, null, ['default_price' => [$old, setting('default_subscription_price')]]);
    flash('success', 'Settings saved.' . ($old !== setting('default_subscription_price') ? ' New default price applies to renewals of shops without a custom price.' : ''));
    redirect('admin/settings.php');
}
admin_header('Settings');
$val = fn($k) => setting($k);
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
<div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-calendar-check"></i> Subscriptions</div><div class="p-card-body">
    <?= f_input('default_subscription_price', $fields['default_subscription_price'][0], $val('default_subscription_price'), 'number', ['min' => 0, 'step' => 1]) ?>
    <p class="form-text mt-n2">Shops with a custom price keep it. Changing this does not alter periods already paid.</p>
    <div class="row"><div class="col-6"><?= f_input('default_grace_days', $fields['default_grace_days'][0], $val('default_grace_days'), 'number', ['min' => 0]) ?></div>
        <div class="col-6"><?= f_input('reminder_days_before', $fields['reminder_days_before'][0], $val('reminder_days_before'), 'number', ['min' => 0]) ?></div></div>
</div></div>
<div class="p-card"><div class="p-card-head"><i class="bi bi-shield-lock"></i> Files &amp; privacy</div><div class="p-card-body">
    <div class="row"><div class="col-6"><?= f_input('default_retention_days', $fields['default_retention_days'][0], $val('default_retention_days'), 'number', ['min' => 1]) ?></div>
        <div class="col-6"><?= f_input('max_retention_days', $fields['max_retention_days'][0], $val('max_retention_days'), 'number', ['min' => 1]) ?></div>
        <div class="col-6"><?= f_input('max_upload_mb', $fields['max_upload_mb'][0], $val('max_upload_mb'), 'number', ['min' => 1, 'max' => 100]) ?></div>
        <div class="col-6"><?= f_input('temp_upload_hours', $fields['temp_upload_hours'][0], $val('temp_upload_hours'), 'number', ['min' => 1]) ?></div></div>
    <?= f_textarea('privacy_notice', 'Privacy notice shown to customers', $val('privacy_notice'), 4) ?>
    <p class="form-text">The server's PHP <code>upload_max_filesize</code> is <?= e(ini_get('upload_max_filesize')) ?> and <code>post_max_size</code> <?= e(ini_get('post_max_size')) ?>.</p>
</div></div></div>
<div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-gear"></i> Platform</div><div class="p-card-body">
    <?php foreach (['platform_name', 'platform_tagline', 'support_phone', 'support_email'] as $k) echo f_input($k, $fields[$k][0], $val($k), $fields[$k][1]); ?>
</div></div>
<div class="p-card"><div class="p-card-head"><i class="bi bi-phone"></i> Mobile app links</div><div class="p-card-body">
    <p class="small text-muted">Used for Android App Links (<code>/.well-known/assetlinks.json</code>) and iOS Universal Links (<code>/.well-known/apple-app-site-association</code>) so a scanned QR opens the app when installed — and the website otherwise.</p>
    <?php foreach (['android_package', 'android_sha256', 'ios_app_id', 'play_store_url', 'app_store_url'] as $k) echo f_input($k, $fields[$k][0], $val($k), $fields[$k][1]); ?>
    <?= f_switch('show_install_prompt', 'Show an optional “Get the app” prompt on the website', setting('show_install_prompt', '1') === '1') ?>
</div></div></div>
</div>
<button class="btn btn-primary">Save settings</button>
</form>
<?php admin_footer();
