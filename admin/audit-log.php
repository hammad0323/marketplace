<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('audit.view');
$adminF = input_int('admin', 0, 'get');
$action = input('action', '', 'get');
$where = ['1=1'];
$params = [];
if ($adminF) { $where[] = 'l.admin_id = ?'; $params[] = $adminF; }
if ($action !== '') { $where[] = 'l.action LIKE ?'; $params[] = '%' . addcslashes($action, '%_\\') . '%'; }
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM admin_audit_logs l WHERE $w", $params);
$pg = paginate($total, 50, input_int('page', 1, 'get'));
$rows = db_all("SELECT l.*, a.name admin_name FROM admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE $w ORDER BY l.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Audit log', 'audit-log');
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
  <select class="form-select form-select-sm" style="width:200px" name="admin"><option value="">All administrators</option><?php foreach (db_all('SELECT id, name FROM admins ORDER BY name') as $a): ?><option value="<?= (int) $a['id'] ?>"<?= $adminF === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <input class="form-control form-control-sm" style="width:200px" name="action" value="<?= e($action) ?>" placeholder="Action contains…">
  <button class="btn btn-sm btn-outline-primary">Filter</button>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle">
  <thead><tr><th>When</th><th>Administrator</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td class="text-nowrap"><small><?= e(format_date($r['created_at'], true)) ?></small></td><td><?= e($r['admin_name'] ?: '—') ?></td><td><code><?= e($r['action']) ?></code></td><td><small><?= e($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></small></td>
      <td><small class="text-muted"><?= e(mb_substr((string) $r['details'], 0, 200)) ?></small></td><td><small><?= e($r['ip_address']) ?></small></td></tr>
  <?php endforeach; ?></tbody></table></div><div class="card-body border-top"><?= admin_pager($pg) ?></div></div>
<?php admin_footer();
