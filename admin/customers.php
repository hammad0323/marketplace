<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $user = $userId ? db_select_one($conn, 'SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$userId]) : null;

    if (!$user || $user['role_slug'] !== 'customer') {
        flash_set('danger', 'Customer not found.');
        redirect('/admin/customers.php');
    }

    if ($action === 'block') {
        db_execute($conn, 'UPDATE users SET status = "blocked" WHERE id = ?', [$userId]);
        $msg = 'Customer blocked.';
    } elseif ($action === 'unblock') {
        db_execute($conn, 'UPDATE users SET status = "active" WHERE id = ?', [$userId]);
        $msg = 'Customer unblocked.';
    } elseif ($action === 'delete') {
        db_execute($conn, 'UPDATE users SET status = "deleted", email = CONCAT("deleted-", id, "-", email) WHERE id = ?', [$userId]);
        $msg = 'Customer deleted.';
    } else {
        flash_set('danger', 'Invalid action.');
        redirect('/admin/customers.php');
    }

    log_audit($conn, (int) $admin['id'], 'user', $userId, $action);
    flash_set('success', $msg);
    redirect('/admin/customers.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$q = clean_input($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = ['r.slug = "customer"'];
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

$pg = paginate($conn, "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE $whereSql", $params, $page, 20);

$customers = db_select(
    $conn,
    "SELECT u.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = u.id) AS booking_count,
        (SELECT COUNT(*) FROM trips t WHERE t.user_id = u.id) AS trip_count
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE $whereSql ORDER BY u.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$adminPageTitle = 'Customers';
$adminActive = 'customers';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All customers</h3>
    <form method="get" style="display:flex;gap:10px;">
      <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search name or email" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;min-width:240px;">
      <button class="btn-w btn-outline btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <?php if ($customers): ?>
  <table class="table-w">
    <thead><tr><th>Name</th><th>Email</th><th>Bookings</th><th>Trips</th><th>Status</th><th>Joined</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><strong><?php echo e($c['name']); ?></strong></td>
          <td><?php echo e($c['email']); ?></td>
          <td><?php echo (int) $c['booking_count']; ?></td>
          <td><?php echo (int) $c['trip_count']; ?></td>
          <td><?php echo status_badge($c['status']); ?></td>
          <td><?php echo e(format_date($c['created_at'])); ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <?php if ($c['status'] === 'active'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Block <?php echo e($c['name']); ?>?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int) $c['id']; ?>">
                <input type="hidden" name="action" value="block">
                <button type="submit" class="btn-w btn-outline btn-sm">Block</button>
              </form>
            <?php elseif ($c['status'] === 'blocked'): ?>
              <form method="post" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int) $c['id']; ?>">
                <input type="hidden" name="action" value="unblock">
                <button type="submit" class="btn-w btn-primary btn-sm">Unblock</button>
              </form>
            <?php endif; ?>
            <?php if ($c['status'] !== 'deleted'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo e($c['name']); ?>? This deactivates their account.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int) $c['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pg['total_pages'] > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
      <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
        <a href="?page=<?php echo $i; ?>&q=<?php echo e($q); ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-people"></i></div><h4>No customers found</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
