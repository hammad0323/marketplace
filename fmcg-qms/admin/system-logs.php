<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$page = max(1, get_int('page', 1));
$perPage = 30;
$total = db_count('activity_logs');
$p = paginate($total, $page, $perPage);
$logs = db_all("SELECT al.*, c.name AS company_name FROM activity_logs al LEFT JOIN companies c ON c.id=al.company_id
    ORDER BY al.created_at DESC LIMIT $perPage OFFSET {$p['offset']}", []);

$pageTitle = 'System Logs';
$activeMenu = 'system-logs';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">System Activity Logs</h4><p class="text-muted mb-0 small">Full audit trail across all companies</p></div>
<div class="qc-card">
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Time</th><th>Company</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="small text-muted"><?= fmt_datetime($l['created_at']) ?></td>
          <td class="small"><?= out($l['company_name'] ?? 'Platform') ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary text-capitalize"><?= out($l['action']) ?></span></td>
          <td class="small text-capitalize"><?= out(str_replace('_',' ',$l['module'])) ?></td>
          <td class="small"><?= out($l['description']) ?></td>
          <td class="small text-muted"><?= out($l['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="6" class="text-center text-muted py-4">No logs yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center mt-2">
    <span class="small text-muted"><?= $total ?> total entries</span>
    <?= pagination_links($p['page'], $p['totalPages'], base_url('admin/system-logs.php')) ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
