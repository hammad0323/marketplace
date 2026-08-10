<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$groupLabels = [
    'general' => ['label' => 'General', 'icon' => 'bi-gear'],
    'finance' => ['label' => 'Finance', 'icon' => 'bi-cash-stack'],
    'social' => ['label' => 'Social Media', 'icon' => 'bi-share'],
    'maps' => ['label' => 'Maps', 'icon' => 'bi-map'],
    'email' => ['label' => 'Email / SMTP', 'icon' => 'bi-envelope'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($_POST as $key => $value) {
        if ($key === 'csrf_token') {
            continue;
        }
        db_execute($conn, 'UPDATE settings SET setting_value = ? WHERE setting_key = ?', [clean_input($value), $key]);
    }
    log_audit($conn, (int) $admin['id'], 'settings', null, 'update');
    flash_set('success', 'Settings saved.');
    redirect('/admin/settings.php');
}

$rows = db_select($conn, 'SELECT * FROM settings ORDER BY setting_group, setting_key');
$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['setting_group']][] = $row;
}

$fieldLabels = [
    'site_name' => 'Site name', 'site_tagline' => 'Tagline', 'site_logo' => 'Logo URL', 'site_favicon' => 'Favicon URL',
    'contact_email' => 'Contact email', 'contact_phone' => 'Contact phone', 'default_currency' => 'Default currency',
    'default_commission_percent' => 'Default commission %', 'service_fee_percent' => 'Service fee %',
    'facebook_url' => 'Facebook URL', 'instagram_url' => 'Instagram URL', 'twitter_url' => 'Twitter/X URL',
    'maps_provider' => 'Maps provider', 'maps_api_key' => 'Maps API key',
    'smtp_host' => 'SMTP host', 'smtp_port' => 'SMTP port', 'smtp_username' => 'SMTP username', 'smtp_password' => 'SMTP password',
    'smtp_encryption' => 'Encryption (none/tls/ssl)', 'smtp_from_name' => 'From name', 'smtp_from_email' => 'From email',
];

$adminPageTitle = 'Settings';
$adminActive = 'settings';
require __DIR__ . '/_layout_top.php';
?>

<form method="post">
  <?php echo csrf_field(); ?>
  <?php foreach ($groupLabels as $groupKey => $meta): ?>
    <?php if (empty($grouped[$groupKey])) continue; ?>
    <div class="panel">
      <div class="panel-head"><h3><i class="bi <?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></h3></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <?php foreach ($grouped[$groupKey] as $s): ?>
          <div>
            <label style="display:block;font-size:13px;font-weight:600;margin:12px 0 6px;"><?php echo e($fieldLabels[$s['setting_key']] ?? $s['setting_key']); ?></label>
            <input type="<?php echo strpos($s['setting_key'], 'password') !== false ? 'password' : 'text'; ?>" name="<?php echo e($s['setting_key']); ?>" value="<?php echo e($s['setting_value']); ?>" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn-w btn-primary">Save all settings</button>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
