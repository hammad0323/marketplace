<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('audit.view');
$w = '1=1'; $p = [];
if (($a = get('action')) !== '') { $w .= ' AND l.action LIKE ?'; $p[] = '%' . $a . '%'; }
if ((int)get('admin')) { $w .= ' AND l.admin_id = ?'; $p[] = (int)get('admin'); }
$pg = paginate((int)db_val("SELECT COUNT(*) FROM admin_audit_logs l WHERE $w", $p), 50, (int)get('page', 1));
$rows = db_all("SELECT l.*, a.name FROM admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE $w ORDER BY l.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $p);
$admin_title = 'Audit log';
require __DIR__ . '/partials/header.php';
?>
<form class="d-flex gap-2 mb-3"><input class="form-control form-control-sm" name="action" value="<?= e(get('action')) ?>" placeholder="Action contains… (e.g. order, product, login)" style="width:300px">
  <select class="form-select form-select-sm" name="admin" style="width:200px"><option value="">All admins</option><?php foreach (db_all('SELECT id, name FROM admins') as $a): ?><option value="<?= (int)$a['id'] ?>"<?= (int)get('admin') === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="small text-nowrap"><?= e($r['created_at']) ?></td><td><?= e($r['name'] ?? '—') ?></td><td><code><?= e($r['action']) ?></code></td><td class="small"><?= e($r['entity_type']) ?> <?= $r['entity_id'] ? '#' . (int)$r['entity_id'] : '' ?></td><td class="small text-muted" style="max-width:420px;word-break:break-word"><?= e(str_limit($r['details'], 240)) ?></td><td class="small"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="mt-3"><?= admin_pager($pg) ?></div>
<?php require __DIR__ . '/partials/footer.php';
