<?php
require __DIR__ . '/includes/init.php';
admin_require('settings');
$groups = [
    'Store information' => [
        'site_name' => ['Store name', 'text'], 'site_tagline' => ['Tagline', 'text'], 'store_phone' => ['Phone', 'text'], 'store_whatsapp' => ['WhatsApp number', 'text'],
        'store_email' => ['Email', 'email'], 'store_address' => ['Address', 'text'], 'store_city' => ['City', 'text'], 'opening_hours' => ['Opening hours (display)', 'text'],
        'store_lat' => ['Map latitude', 'text'], 'store_lng' => ['Map longitude', 'text'], 'announcement' => ['Announcement bar text', 'text'],
    ],
    'Social links' => ['facebook_url' => ['Facebook URL', 'url'], 'instagram_url' => ['Instagram URL', 'url'], 'youtube_url' => ['YouTube URL', 'url'], 'tiktok_url' => ['TikTok URL', 'url']],
    'Orders & inventory' => [
        'order_prefix' => ['Order number prefix', 'text'], 'currency_symbol' => ['Currency symbol', 'text'], 'products_per_page' => ['Products per page', 'number'],
        'stock_policy' => ['When to deduct stock', 'select', ['placed' => 'When the order is placed (recommended)', 'confirmed' => 'When the order is confirmed', 'completed' => 'When the order is completed']],
    ],
    'Monthly Rashan' => ['rashan_title' => ['Page title', 'text'], 'rashan_intro' => ['Intro text', 'textarea'], 'rashan_default_budget' => ['Default monthly budget', 'number']],
    'Notifications' => [
        'notify_email' => ['Send new-order emails to', 'email'], 'notify_whatsapp' => ['Send new-order WhatsApp to (number)', 'text'],
        'whatsapp_enabled' => ['Enable WhatsApp Cloud API sending', 'bool'], 'whatsapp_customer_updates' => ['Send order status updates to customers on WhatsApp', 'bool'],
    ],
    'Receipts & invoices' => ['pos_receipt_footer' => ['POS receipt footer', 'textarea'], 'invoice_footer' => ['Invoice footer', 'textarea']],
];
if (is_post()) {
    csrf_check();
    foreach ($groups as $fields) {
        foreach ($fields as $k => $f) {
            if ($f[1] === 'bool') setting_set($k, input_int($k));
            elseif ($f[1] === 'select') { if (isset($f[2][input($k)])) setting_set($k, input($k)); }
            elseif ($f[1] === 'number') setting_set($k, (string) max(0, input_float($k, 0.0) + 0));
            else setting_set($k, input($k));
        }
    }
    cache_clear();
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}
admin_header('Website Settings', 'settings');
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
<?php foreach ($groups as $title => $fields): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><?= e($title) ?></div><div class="card-body">
  <?php foreach ($fields as $k => $f):
    if ($f[1] === 'bool') echo af_check($k, $f[0], setting($k) === '1');
    elseif ($f[1] === 'select') echo af_select($k, $f[0], $f[2], setting($k, array_key_first($f[2])), [], $k === 'stock_policy' ? 'Stock is always validated at checkout. Cancelling an order returns deducted stock automatically.' : '');
    elseif ($f[1] === 'textarea') echo af_textarea($k, $f[0], setting($k), ['rows' => 2]);
    else echo af_text($k, $f[0], setting($k), ['type' => $f[1]]);
  endforeach; ?>
  <?php if ($title === 'Notifications'): ?><div class="small text-muted">API credentials (SMTP, WhatsApp token &amp; phone ID) are stored in <code>includes/config.php</code> — never in the database. Status: email <?= SMTP_HOST ? '<span class="text-success">SMTP configured</span>' : 'using PHP mail()' ?> · WhatsApp API <?= WHATSAPP_TOKEN ? '<span class="text-success">configured</span>' : '<span class="text-warning">not configured</span>' ?>.</div><?php endif; ?>
  </div></div></div>
<?php endforeach; ?>
</div>
<div class="mt-3"><button class="btn btn-brand btn-lg">Save settings</button></div>
</form>
<?php admin_footer();
