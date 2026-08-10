<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $cityId = (int) ($_POST['city_id'] ?? 0);
    $city = $cityId ? db_select_one($conn, 'SELECT * FROM cities WHERE id = ?', [$cityId]) : null;
    if (!$city) {
        flash_set('danger', 'City not found.');
        redirect('/admin/cities.php');
    }
    if ($action === 'toggle') {
        db_execute($conn, 'UPDATE cities SET is_active = ? WHERE id = ?', [$city['is_active'] ? 0 : 1, $cityId]);
        flash_set('success', 'City visibility updated.');
    } elseif ($action === 'feature') {
        db_execute($conn, 'UPDATE cities SET is_featured = ? WHERE id = ?', [$city['is_featured'] ? 0 : 1, $cityId]);
        flash_set('success', 'City featured status updated.');
    } elseif ($action === 'delete') {
        $serviceCount = db_count($conn, 'SELECT COUNT(*) FROM services WHERE city_id = ?', [$cityId]);
        if ($serviceCount > 0) {
            flash_set('danger', 'Cannot delete a city with services. Hide it instead.');
        } else {
            db_execute($conn, 'DELETE FROM cities WHERE id = ?', [$cityId]);
            flash_set('success', 'City deleted.');
        }
    }
    log_audit($conn, (int) $admin['id'], 'city', $cityId, $action);
    redirect('/admin/cities.php');
}

$cities = db_select(
    $conn,
    'SELECT c.*, co.name AS country_name,
        (SELECT COUNT(*) FROM services s WHERE s.city_id = c.id) AS service_count,
        (SELECT COUNT(*) FROM providers p WHERE p.city_id = c.id) AS provider_count
     FROM cities c JOIN countries co ON co.id = c.country_id
     ORDER BY c.sort_order, c.name'
);

$adminPageTitle = 'Cities';
$adminActive = 'cities';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All cities</h3>
    <a href="/admin/city-form.php" class="btn-w btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New city</a>
  </div>

  <?php if ($cities): ?>
  <table class="table-w">
    <thead><tr><th>City</th><th>Country</th><th>Providers</th><th>Services</th><th>Featured</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($cities as $city): ?>
        <tr>
          <td><strong><?php echo e($city['name']); ?></strong></td>
          <td><?php echo e($city['country_name']); ?></td>
          <td><?php echo (int) $city['provider_count']; ?></td>
          <td><?php echo (int) $city['service_count']; ?></td>
          <td>
            <form method="post" style="display:inline;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="city_id" value="<?php echo (int) $city['id']; ?>">
              <input type="hidden" name="action" value="feature">
              <button type="submit" class="btn-w btn-sm <?php echo $city['is_featured'] ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi bi-star<?php echo $city['is_featured'] ? '-fill' : ''; ?>"></i></button>
            </form>
          </td>
          <td><?php echo status_badge($city['is_active'] ? 'active' : 'blocked'); ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="/admin/city-form.php?id=<?php echo (int) $city['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a>
            <form method="post" style="display:inline;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="city_id" value="<?php echo (int) $city['id']; ?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit" class="btn-w btn-outline btn-sm"><?php echo $city['is_active'] ? 'Hide' : 'Show'; ?></button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo e($city['name']); ?>?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="city_id" value="<?php echo (int) $city['id']; ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-geo-alt"></i></div><h4>No cities yet</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
