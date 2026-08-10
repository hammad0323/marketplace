<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $plan = db_select_one($conn, 'SELECT * FROM membership_plans WHERE id = ? AND is_active = 1', [$planId]);
    if (!$plan) {
        flash_set('danger', 'Invalid plan.');
        redirect('/provider/membership.php');
    }

    if ((float) $plan['price'] <= 0) {
        activate_membership($conn, (int) $provider['id'], $planId);
        flash_set('success', 'You are now on the ' . $plan['name'] . ' plan.');
        redirect('/provider/membership.php');
    }

    $bankGateway = db_select_one($conn, 'SELECT id FROM payment_gateways WHERE slug = "bank-transfer" AND is_active = 1');
    db_insert_get_id(
        $conn,
        'INSERT INTO payments (user_id, plan_id, gateway_id, amount, currency, status) VALUES (?, ?, ?, ?, "USD", "pending")',
        [(int) $user['id'], $planId, $bankGateway['id'] ?? null, $plan['price']]
    );
    flash_set('info', 'Upgrade request created for the ' . $plan['name'] . ' plan ($' . number_format((float) $plan['price'], 2) . '). Follow the bank transfer instructions below — your plan activates once admin confirms payment.');
    redirect('/provider/membership.php');
}

$plans = db_select($conn, 'SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY sort_order');
$payments = db_select($conn, 'SELECT p.*, mp.name AS plan_name FROM payments p LEFT JOIN membership_plans mp ON mp.id = p.plan_id WHERE p.user_id = ? AND p.plan_id IS NOT NULL ORDER BY p.created_at DESC LIMIT 10', [(int) $user['id']]);
$currentPlanId = (int) $provider['membership_plan_id'];

$pageTitle = 'Membership';
$providerActiveTab = 'membership';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-award"></i> Provider</span>
      <h1 class="section-heading">Membership plans</h1>
      <p class="section-sub">Unlock more services, gallery space and priority placement in search.</p>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <div class="provider-grid" style="grid-template-columns:repeat(3,1fr);">
      <?php foreach ($plans as $plan): $isCurrent = $plan['id'] == $currentPlanId; ?>
        <div class="panel" style="text-align:center;<?php echo $isCurrent ? 'border-color:var(--purple);box-shadow:var(--shadow-purple);' : ''; ?>">
          <?php if ($isCurrent): ?><span class="badge-pill" style="position:static;display:inline-flex;margin-bottom:10px;">Current plan</span><?php endif; ?>
          <h3 style="font-size:19px;font-weight:800;"><?php echo e($plan['name']); ?></h3>
          <div style="font-size:30px;font-weight:800;margin:10px 0;">
            <?php echo $plan['price'] > 0 ? format_price($plan['price']) : 'Free'; ?>
            <?php if ($plan['price'] > 0): ?><span style="font-size:13px;font-weight:500;color:var(--ink-mute);">/ <?php echo e($plan['billing_cycle']); ?></span><?php endif; ?>
          </div>
          <ul style="list-style:none;padding:0;margin:18px 0;text-align:left;font-size:13.5px;color:var(--ink-soft);">
            <li style="padding:6px 0;"><i class="bi bi-check-circle-fill" style="color:var(--success);"></i> <?php echo $plan['max_services'] ? (int) $plan['max_services'] . ' services' : 'Unlimited services'; ?></li>
            <li style="padding:6px 0;"><i class="bi bi-check-circle-fill" style="color:var(--success);"></i> <?php echo (int) $plan['max_gallery_images']; ?> gallery images per service</li>
            <li style="padding:6px 0;"><i class="bi bi-check-circle-fill" style="color:var(--success);"></i> Search priority: <?php echo (int) $plan['priority_ranking']; ?></li>
          </ul>
          <?php if (!$isCurrent): ?>
            <form method="post" onsubmit="return confirm('Switch to the <?php echo e($plan['name']); ?> plan?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id']; ?>">
              <button type="submit" class="btn-w btn-primary btn-block"><?php echo $plan['price'] > 0 ? 'Upgrade' : 'Switch to Free'; ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:6px;">Bank transfer instructions</h3>
      <p class="form-hint">For paid plans, transfer the plan amount to our account below and reference your business name. Your plan activates once our team confirms the transfer (usually within one business day).</p>
      <p style="font-size:13.5px;margin-top:10px;"><strong>Account name:</strong> Wanderly Ltd &nbsp; <strong>IBAN:</strong> XX00 0000 0000 0000 &nbsp; <strong>Reference:</strong> <?php echo e($provider['business_name']); ?></p>
    </div>

    <?php if ($payments): ?>
    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:14px;">Payment history</h3>
      <table class="table-w">
        <thead><tr><th>Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr><td><?php echo e($p['plan_name']); ?></td><td><?php echo format_price($p['amount']); ?></td><td><?php echo status_badge($p['status'] === 'completed' ? 'approved' : $p['status']); ?></td><td><?php echo e(format_date($p['created_at'])); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
