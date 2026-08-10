<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $name = clean_input($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $cycle = in_array($_POST['billing_cycle'] ?? '', ['monthly', 'yearly', 'lifetime'], true) ? $_POST['billing_cycle'] : 'monthly';
    $maxServices = $_POST['max_services'] !== '' ? (int) $_POST['max_services'] : null;
    $maxImages = (int) ($_POST['max_gallery_images'] ?? 0);
    $priority = (int) ($_POST['priority_ranking'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash_set('danger', 'Plan name is required.');
        redirect('/admin/membership-plans.php' . ($id ? '?edit=' . $id : ''));
    }

    if ($id) {
        db_execute(
            $conn,
            'UPDATE membership_plans SET name=?, price=?, billing_cycle=?, max_services=?, max_gallery_images=?, priority_ranking=?, is_active=? WHERE id=?',
            [$name, $price, $cycle, $maxServices, $maxImages, $priority, $isActive, $id]
        );
        flash_set('success', 'Plan updated.');
    } else {
        db_insert_get_id(
            $conn,
            'INSERT INTO membership_plans (name, price, billing_cycle, max_services, max_gallery_images, priority_ranking, is_active, sort_order) VALUES (?,?,?,?,?,?,?, (SELECT m FROM (SELECT COALESCE(MAX(sort_order),0)+1 AS m FROM membership_plans) t))',
            [$name, $price, $cycle, $maxServices, $maxImages, $priority, $isActive]
        );
        flash_set('success', 'Plan created.');
    }
    log_audit($conn, (int) $admin['id'], 'membership_plan', $id, 'save');
    redirect('/admin/membership-plans.php');
}

$plans = db_select($conn, 'SELECT mp.*, (SELECT COUNT(*) FROM providers p WHERE p.membership_plan_id = mp.id) AS provider_count FROM membership_plans mp ORDER BY sort_order');
$editingPlan = $editId ? db_select_one($conn, 'SELECT * FROM membership_plans WHERE id = ?', [$editId]) : null;

$adminPageTitle = 'Membership Plans';
$adminActive = 'memberships';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head"><h3><?php echo $editingPlan ? 'Edit plan' : 'New plan'; ?></h3></div>
  <form method="post" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;align-items:end;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo (int) ($editingPlan['id'] ?? 0); ?>">
    <div><label style="font-size:13px;font-weight:600;">Name</label><input type="text" name="name" value="<?php echo e($editingPlan['name'] ?? ''); ?>" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <div><label style="font-size:13px;font-weight:600;">Price (USD)</label><input type="number" step="0.01" name="price" value="<?php echo e($editingPlan['price'] ?? '0'); ?>" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <div>
      <label style="font-size:13px;font-weight:600;">Billing cycle</label>
      <select name="billing_cycle" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
        <?php foreach (['monthly', 'yearly', 'lifetime'] as $c): ?>
          <option value="<?php echo $c; ?>" <?php echo ($editingPlan['billing_cycle'] ?? 'monthly') === $c ? 'selected' : ''; ?>><?php echo ucfirst($c); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label style="font-size:13px;font-weight:600;">Max services (blank = unlimited)</label><input type="number" name="max_services" value="<?php echo e($editingPlan['max_services'] ?? ''); ?>" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <div><label style="font-size:13px;font-weight:600;">Max gallery images</label><input type="number" name="max_gallery_images" value="<?php echo e($editingPlan['max_gallery_images'] ?? '10'); ?>" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <div><label style="font-size:13px;font-weight:600;">Search priority</label><input type="number" name="priority_ranking" value="<?php echo e($editingPlan['priority_ranking'] ?? '0'); ?>" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" value="1" <?php echo ($editingPlan['is_active'] ?? 1) ? 'checked' : ''; ?> style="width:auto;"> Active</label>
    <div style="grid-column:span 2;text-align:right;">
      <?php if ($editingPlan): ?><a href="/admin/membership-plans.php" class="btn-w btn-outline btn-sm">Cancel</a><?php endif; ?>
      <button type="submit" class="btn-w btn-primary btn-sm"><?php echo $editingPlan ? 'Save' : 'Create plan'; ?></button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h3>All plans</h3></div>
  <table class="table-w">
    <thead><tr><th>Name</th><th>Price</th><th>Cycle</th><th>Max services</th><th>Providers</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td><strong><?php echo e($p['name']); ?></strong></td>
          <td><?php echo $p['price'] > 0 ? format_price($p['price']) : 'Free'; ?></td>
          <td><?php echo e($p['billing_cycle']); ?></td>
          <td><?php echo $p['max_services'] ? (int) $p['max_services'] : 'Unlimited'; ?></td>
          <td><?php echo (int) $p['provider_count']; ?></td>
          <td><?php echo status_badge($p['is_active'] ? 'active' : 'blocked'); ?></td>
          <td style="text-align:right;"><a href="?edit=<?php echo (int) $p['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
