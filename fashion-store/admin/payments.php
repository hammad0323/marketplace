<?php
$pageTitle = 'Payment Methods';
require_once __DIR__ . '/includes/admin_header.php';

$fields = [
    'cod' => ['min_order' => 'Minimum Order', 'max_order' => 'Maximum Order (0 = no limit)', 'fee' => 'COD Handling Fee'],
    'easypaisa' => ['merchant_id' => 'Merchant ID', 'api_key' => 'API Key'],
    'jazzcash' => ['merchant_id' => 'Merchant ID', 'api_key' => 'API Key / Integrity Salt'],
    'stripe' => ['publishable_key' => 'Publishable Key', 'secret_key' => 'Secret Key'],
    'square' => ['app_id' => 'Application ID', 'access_token' => 'Access Token'],
    'moneris' => ['store_id' => 'Store ID', 'api_token' => 'API Token'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $res = mysqli_query($mysqli, "SELECT * FROM payment_methods");
    while ($m = mysqli_fetch_assoc($res)) {
        $code = $m['code'];
        $enabled = isset($_POST['enabled'][$code]) ? 1 : 0;
        $settings = [];
        foreach ($fields[$code] ?? [] as $key => $label) {
            $settings[$key] = trim($_POST['settings'][$code][$key] ?? '');
        }
        $stmt = mysqli_prepare($mysqli, "UPDATE payment_methods SET is_enabled=?, settings=? WHERE code=?");
        $json = json_encode($settings);
        mysqli_stmt_bind_param($stmt, 'iss', $enabled, $json, $code);
        mysqli_stmt_execute($stmt);
    }
    flash_set('success', 'Payment settings updated.');
    redirect('payments.php');
}

$methods = mysqli_query($mysqli, "SELECT * FROM payment_methods ORDER BY sort_order");
?>
<h1 class="page-title mb-4">Payment Methods</h1>
<form method="post">
<?= csrf_field() ?>
<div class="row g-3">
<?php while ($m = mysqli_fetch_assoc($methods)):
  $settings = json_decode($m['settings'] ?: '{}', true) ?: [];
?>
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0"><?= e($m['name']) ?></h2>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="enabled[<?= e($m['code']) ?>]" <?= $m['is_enabled'] ? 'checked' : '' ?>>
        </div>
      </div>
      <p class="small text-muted"><?= e($m['description']) ?></p>
      <?php foreach ($fields[$m['code']] ?? [] as $key => $label): ?>
        <div class="mb-2">
          <label class="form-label small"><?= e($label) ?></label>
          <input type="text" name="settings[<?= e($m['code']) ?>][<?= e($key) ?>]" class="form-control form-control-sm" value="<?= e($settings[$key] ?? '') ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endwhile; ?>
</div>
<button class="btn btn-primary text-white mt-4"><i class="bi bi-check-lg"></i> Save Payment Settings</button>
</form>
<p class="small text-muted mt-3">Note: EasyPaisa, JazzCash, Stripe, Square and Moneris require valid merchant credentials from each provider to process live payments. Store credentials here once obtained from your payment provider account.</p>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
