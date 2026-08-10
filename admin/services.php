<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $service = $serviceId ? db_select_one($conn, 'SELECT * FROM services WHERE id = ?', [$serviceId]) : null;
    if (!$service) {
        flash_set('danger', 'Service not found.');
        redirect('/admin/services.php');
    }

    if ($action === 'approve') {
        db_execute($conn, 'UPDATE services SET status = "approved" WHERE id = ?', [$serviceId]);
        $provider = db_select_one($conn, 'SELECT user_id FROM providers WHERE id = ?', [(int) $service['provider_id']]);
        if ($provider) {
            db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "service_approved", "Service approved", ?, "/provider/services.php")', [(int) $provider['user_id'], $service['title'] . ' is now live.']);
        }
        $msg = 'Service approved.';
    } elseif ($action === 'reject') {
        db_execute($conn, 'UPDATE services SET status = "rejected" WHERE id = ?', [$serviceId]);
        $msg = 'Service rejected.';
    } elseif ($action === 'feature') {
        db_execute($conn, 'UPDATE services SET is_featured = ? WHERE id = ?', [$service['is_featured'] ? 0 : 1, $serviceId]);
        $msg = 'Feature status updated.';
    } elseif ($action === 'delete') {
        db_execute($conn, 'DELETE FROM services WHERE id = ?', [$serviceId]);
        $msg = 'Service deleted.';
    } else {
        flash_set('danger', 'Invalid action.');
        redirect('/admin/services.php');
    }
    log_audit($conn, (int) $admin['id'], 'service', $serviceId, $action);
    flash_set('success', $msg);
    redirect('/admin/services.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$statusFilter = clean_input($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$pg = paginate($conn, "SELECT COUNT(*) FROM services s WHERE $whereSql", $params, $page, 15);

$services = db_select(
    $conn,
    "SELECT s.*, p.business_name, cat.name AS category_name,
        (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
     FROM services s JOIN providers p ON p.id = s.provider_id LEFT JOIN categories cat ON cat.id = s.category_id
     WHERE $whereSql ORDER BY s.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$adminPageTitle = 'Services';
$adminActive = 'services';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All services</h3>
    <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'approved', 'rejected', 'hidden'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($services): ?>
  <table class="table-w">
    <thead><tr><th></th><th>Title</th><th>Provider</th><th>Category</th><th>Price</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($services as $s): ?>
        <tr>
          <td><div style="width:52px;height:40px;border-radius:8px;background:var(--purple-soft);overflow:hidden;"><?php if ($s['cover']): ?><img src="<?php echo e($s['cover']); ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?></div></td>
          <td><strong><?php echo e($s['title']); ?></strong> <?php if ($s['is_featured']): ?><i class="bi bi-star-fill" style="color:var(--warning);"></i><?php endif; ?></td>
          <td><?php echo e($s['business_name']); ?></td>
          <td><?php echo e($s['category_name'] ?? '—'); ?></td>
          <td><?php echo format_price($s['price']); ?></td>
          <td><?php echo status_badge($s['status']); ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <?php if ($s['status'] === 'pending'): ?>
              <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn-w btn-primary btn-sm">Approve</button></form>
              <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn-w btn-outline btn-sm">Reject</button></form>
            <?php endif; ?>
            <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="action" value="feature"><button class="btn-w btn-outline btn-sm"><?php echo $s['is_featured'] ? 'Unfeature' : 'Feature'; ?></button></form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this service?');"><?php echo csrf_field(); ?><input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="action" value="delete"><button class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pg['total_pages'] > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
      <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo e($statusFilter); ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-list-ul"></i></div><h4>No services match this filter</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
