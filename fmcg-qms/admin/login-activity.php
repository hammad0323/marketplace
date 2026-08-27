<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$logs = get_login_activity(null, 150);
$companyMap = [];
foreach (db_all("SELECT id, name FROM companies", []) as $c) { $companyMap[$c['id']] = $c['name']; }

$pageTitle = 'Login Activity';
$activeMenu = 'login-activity';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Login Activity</h4><p class="text-muted mb-0 small">Login, logout and failed login attempts across the platform</p></div>
<div class="qc-card">
  <table id="loginTable" class="table table-sm">
    <thead><tr><th>Time</th><th>Company</th><th>Action</th><th>Description</th><th>IP Address</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="small text-muted"><?= fmt_datetime($l['created_at']) ?></td>
        <td class="small"><?= out($companyMap[$l['company_id']] ?? 'Platform') ?></td>
        <td><?php
          $cls = $l['action'] === 'login_failed' ? 'bg-danger-subtle text-danger' : ($l['action'] === 'login' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary');
          echo '<span class="badge ' . $cls . ' text-capitalize">' . out(str_replace('_',' ',$l['action'])) . '</span>';
        ?></td>
        <td class="small"><?= out($l['description']) ?></td>
        <td class="small text-muted"><?= out($l['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>$(function(){ $("#loginTable").DataTable({ order: [[0,"desc"]], pageLength: 25 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
