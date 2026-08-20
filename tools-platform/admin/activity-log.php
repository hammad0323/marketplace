<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$logs = tp_query(
    "SELECT al.*, a.name AS admin_name FROM activity_logs al LEFT JOIN admins a ON a.id = al.admin_id
     ORDER BY al.created_at DESC LIMIT 200"
);

$adminPageTitle = 'Activity Log';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-card">
  <table class="table table-sm tp-datatable">
    <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= e($log['created_at']) ?></td>
        <td><?= e($log['admin_name'] ?? 'System') ?></td>
        <td><?= e($log['action']) ?></td>
        <td><?= e(trim(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? ''))) ?></td>
        <td><?= e($log['details'] ?? '') ?></td>
        <td><?= e($log['ip_address'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
