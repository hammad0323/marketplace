<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');

$tab = ($_GET['tab'] ?? 'admin') === 'user' ? 'user' : 'admin';
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($tab === 'admin') {
    $pg = paginate($conn, 'SELECT COUNT(*) FROM audit_logs', [], $page, 25);
    $rows = db_select(
        $conn,
        "SELECT al.*, u.name AS admin_name FROM audit_logs al LEFT JOIN users u ON u.id = al.admin_id
         ORDER BY al.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
    );
} else {
    $pg = paginate($conn, 'SELECT COUNT(*) FROM activity_logs', [], $page, 25);
    $rows = db_select(
        $conn,
        "SELECT al.*, u.name AS user_name, r.slug AS role_slug FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id LEFT JOIN roles r ON r.id = u.role_id
         ORDER BY al.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
    );
}

$adminActionLabels = [
    'block' => ['icon' => 'bi-slash-circle', 'color' => 'var(--danger)'],
    'unblock' => ['icon' => 'bi-check-circle', 'color' => 'var(--success)'],
    'approve' => ['icon' => 'bi-check-circle', 'color' => 'var(--success)'],
    'reject' => ['icon' => 'bi-x-circle', 'color' => 'var(--danger)'],
    'delete' => ['icon' => 'bi-trash', 'color' => 'var(--danger)'],
    'update' => ['icon' => 'bi-pencil', 'color' => 'var(--purple-600)'],
];

$adminPageTitle = 'Activity Logs';
$adminActive = 'logs';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <div style="display:flex;gap:8px;">
      <a href="?tab=admin" class="btn-w btn-sm <?php echo $tab === 'admin' ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi bi-shield-check"></i> Admin actions</a>
      <a href="?tab=user" class="btn-w btn-sm <?php echo $tab === 'user' ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi bi-person-lines-fill"></i> User activity</a>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-clock-history"></i></div><h4>No activity recorded yet</h4><p>Actions taken in the admin panel<?php echo $tab === 'user' ? ', logins, and registrations' : ''; ?> will show up here.</p></div>
  <?php elseif ($tab === 'admin'): ?>
    <table class="table-w">
      <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>On</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $meta = $adminActionLabels[$r['action']] ?? ['icon' => 'bi-dot', 'color' => 'var(--ink-mute)']; ?>
          <tr>
            <td style="white-space:nowrap;color:var(--ink-mute);font-size:13px;" title="<?php echo e(format_date($r['created_at'], 'M j, Y g:i A')); ?>"><?php echo e(time_ago($r['created_at'])); ?></td>
            <td><?php echo e($r['admin_name'] ?? 'Unknown admin'); ?></td>
            <td><i class="bi <?php echo $meta['icon']; ?>" style="color:<?php echo $meta['color']; ?>;"></i> <?php echo e(ucwords(str_replace('_', ' ', $r['action']))); ?></td>
            <td><?php echo e(ucwords(str_replace('_', ' ', $r['entity_type']))); ?><?php echo $r['entity_id'] ? ' #' . (int) $r['entity_id'] : ''; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="table-w">
      <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Details</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap;color:var(--ink-mute);font-size:13px;" title="<?php echo e(format_date($r['created_at'], 'M j, Y g:i A')); ?>"><?php echo e(time_ago($r['created_at'])); ?></td>
            <td><?php echo e($r['user_name'] ?? 'Unknown user'); ?></td>
            <td><?php echo $r['role_slug'] ? e(ucfirst($r['role_slug'])) : '—'; ?></td>
            <td><?php echo e(ucwords(str_replace('_', ' ', $r['action']))); ?></td>
            <td style="color:var(--ink-mute);"><?php echo e($r['description'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($pg['total_pages'] > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
      <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
        <a href="?tab=<?php echo e($tab); ?>&page=<?php echo $i; ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
