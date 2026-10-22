<?php
require __DIR__ . '/includes/init.php';
admin_require('audit');
$where = '1';
$params = [];
if ($q = input('q', '', 'get')) {
    $where .= ' AND (l.action LIKE ? OR l.entity_type LIKE ? OR l.details LIKE ? OR a.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$total = (int) db_val("SELECT COUNT(*) FROM admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE $where", $params);
$pg = paginate($total, 50);
$rows = db_all("SELECT l.*, a.name FROM admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE $where ORDER BY l.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Audit log');
?>
<form class="a-filters" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search action, entity, admin…"><button class="btn btn-dark">Search</button></form>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td class="text-nowrap small"><?= e(format_datetime($r['created_at'])) ?></td><td><?= e($r['name'] ?? '—') ?></td><td><code><?= e($r['action']) ?></code></td>
<td class="small"><?= e($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td><td class="small text-break" style="max-width:420px"><?= e(str_limit($r['details'], 220)) ?></td><td class="small"><?= e($r['ip_address']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/audit.php')) ?>
<?php admin_footer();
