<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);

if (!$provider) {
    flash_set('danger', 'Provider profile not found.');
    redirect('/provider/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $service = $serviceId ? db_select_one($conn, 'SELECT * FROM services WHERE id = ? AND provider_id = ?', [$serviceId, (int) $provider['id']]) : null;

    if (!$service) {
        flash_set('danger', 'Service not found.');
        redirect('/provider/services.php');
    }

    if ($action === 'toggle' && in_array($service['status'], ['approved', 'hidden'], true)) {
        $newStatus = $service['status'] === 'hidden' ? 'approved' : 'hidden';
        db_execute($conn, 'UPDATE services SET status = ? WHERE id = ?', [$newStatus, $serviceId]);
        flash_set('success', 'Service visibility updated.');
    } elseif ($action === 'delete') {
        db_execute($conn, 'DELETE FROM services WHERE id = ?', [$serviceId]);
        flash_set('success', 'Service deleted.');
    } elseif ($action === 'duplicate') {
        $newId = db_insert_get_id(
            $conn,
            'INSERT INTO services (provider_id, category_id, city_id, title, slug, short_description, description, address, latitude, longitude, price, price_unit, max_guests, status, cancellation_policy)
             SELECT provider_id, category_id, city_id, CONCAT(title, " (Copy)"), ?, short_description, description, address, latitude, longitude, price, price_unit, max_guests, "pending", cancellation_policy
             FROM services WHERE id = ?',
            [unique_slug($conn, 'services', $service['title'] . '-copy'), $serviceId]
        );
        db_execute($conn, 'INSERT INTO service_images (service_id, image_path, is_cover, sort_order) SELECT ?, image_path, is_cover, sort_order FROM service_images WHERE service_id = ?', [$newId, $serviceId]);
        db_execute($conn, 'INSERT INTO service_amenity_map (service_id, amenity_id) SELECT ?, amenity_id FROM service_amenity_map WHERE service_id = ?', [$newId, $serviceId]);
        db_execute($conn, 'INSERT INTO service_field_values (service_id, category_field_id, field_value) SELECT ?, category_field_id, field_value FROM service_field_values WHERE service_id = ?', [$newId, $serviceId]);
        flash_set('success', 'Service duplicated as a draft.');
    }
    redirect('/provider/services.php');
}

$services = db_select(
    $conn,
    'SELECT s.*, c.name AS category_name, (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC, sort_order LIMIT 1) AS cover
     FROM services s LEFT JOIN categories c ON c.id = s.category_id
     WHERE s.provider_id = ? ORDER BY s.created_at DESC',
    [(int) $provider['id']]
);

$maxServices = null;
if (!empty($provider['membership_plan_id'])) {
    $plan = db_select_one($conn, 'SELECT max_services FROM membership_plans WHERE id = ?', [(int) $provider['membership_plan_id']]);
    $maxServices = $plan['max_services'] ?? null;
} else {
    $freePlan = db_select_one($conn, 'SELECT max_services FROM membership_plans WHERE name = "Free"');
    $maxServices = $freePlan['max_services'] ?? null;
}
$atLimit = $maxServices !== null && count($services) >= (int) $maxServices;

$pageTitle = 'My Services';
$providerActiveTab = 'services';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-list-ul"></i> Provider</span>
        <h1 class="section-heading">My services</h1>
        <p class="section-sub"><?php echo count($services); ?><?php echo $maxServices !== null ? ' / ' . (int) $maxServices : ''; ?> services on your current plan.</p>
      </div>
      <?php if ($provider['status'] !== 'approved'): ?>
        <div class="alert-w alert-info" style="margin:0;"><i class="bi bi-info-circle-fill"></i> Your business must be approved before services go live.</div>
      <?php elseif ($atLimit): ?>
        <div class="alert-w alert-info" style="margin:0;display:flex;align-items:center;gap:10px;">
          <i class="bi bi-info-circle-fill"></i> You've reached your plan's service limit.
          <a href="<?php echo url('/provider/membership.php'); ?>" class="btn-w btn-primary btn-sm" style="margin-left:8px;">Upgrade</a>
        </div>
      <?php else: ?>
        <a href="<?php echo url('/provider/service-form.php'); ?>" class="btn-w btn-primary"><i class="bi bi-plus-lg"></i> Add service</a>
      <?php endif; ?>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <?php if ($services): ?>
      <div class="panel">
        <table class="table-w">
          <thead><tr><th></th><th>Title</th><th>Category</th><th>Price</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($services as $s): ?>
              <tr>
                <td><div style="width:52px;height:40px;border-radius:8px;background:var(--purple-soft);overflow:hidden;"><?php if ($s['cover']): ?><img src="<?php echo e($s['cover']); ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?></div></td>
                <td><strong><?php echo e($s['title']); ?></strong></td>
                <td><?php echo e($s['category_name'] ?? '—'); ?></td>
                <td><?php echo format_price($s['price']); ?> / <?php echo e($s['price_unit']); ?></td>
                <td><?php echo status_badge($s['status']); ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <a href="<?php echo url('/provider/service-form.php'); ?>?id=<?php echo (int) $s['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a>
                  <form method="post" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                    <input type="hidden" name="action" value="duplicate">
                    <button type="submit" class="btn-w btn-outline btn-sm"><i class="bi bi-copy"></i></button>
                  </form>
                  <?php if ($s['status'] !== 'pending'): ?>
                  <form method="post" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button type="submit" class="btn-w btn-outline btn-sm"><?php echo $s['status'] === 'hidden' ? 'Show' : 'Hide'; ?></button>
                  </form>
                  <?php endif; ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-list-ul"></i></div>
        <h4>No services yet</h4>
        <p>Add your first hotel room, car, table or experience to start getting bookings.</p>
        <?php if ($provider['status'] === 'approved'): ?><a href="<?php echo url('/provider/service-form.php'); ?>" class="btn-w btn-primary">Add your first service</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
