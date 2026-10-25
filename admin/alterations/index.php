<?php
require __DIR__ . '/../includes/init.php';
admin_require('alterations');
$status = input('status', '', 'get');
$q = input('q', '', 'get');
$where = ['1'];
$params = [];
if (isset(alteration_statuses()[$status])) { $where[] = 'r.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(r.reference LIKE ? OR r.name LIKE ? OR r.phone LIKE ? OR r.city LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM alteration_requests r WHERE $w", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT r.*, (SELECT GROUP_CONCAT(CONCAT(i.qty, '× ', i.garment, ' — ', i.service_name) SEPARATOR '; ') FROM alteration_request_items i WHERE i.request_id = r.id) services
    FROM alteration_requests r WHERE $w ORDER BY r.status = 'new' DESC, r.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$counts = array_column(db_all('SELECT status, COUNT(*) n FROM alteration_requests GROUP BY status'), 'n', 'status');
admin_header('Alteration requests');
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form class="a-filters mb-0" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Reference, name, phone, city">
        <select class="form-select" name="status"><option value="">All statuses</option><?php foreach (alteration_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</option><?php endforeach; ?></select>
        <button class="btn btn-dark">Filter</button></form>
    <a class="btn btn-outline-dark ms-auto" href="<?= e(url('admin/alterations/services.php')) ?>"><i class="bi bi-tags"></i> Services &amp; price list</a>
    <a class="btn btn-outline-dark" href="<?= e(url('alterations')) ?>" target="_blank"><i class="bi bi-eye"></i> Customer page</a>
</div>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th>Reference</th><th>Customer</th><th>Services</th><th>Estimate</th><th>Quote</th><th>Method</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a href="<?= e(url('admin/alterations/view.php?id=' . $r['id'])) ?>"><strong><?= e($r['reference']) ?></strong></a><div class="small text-muted"><?= e(format_datetime($r['created_at'])) ?></div></td>
<td><?= e($r['name']) ?><div class="small text-muted"><?= e($r['phone']) ?> · <?= e($r['city']) ?></div></td>
<td class="small" style="max-width:320px"><?= e(str_limit($r['services'], 140)) ?></td>
<td class="small text-nowrap"><?= e(alteration_price_range((float) $r['est_min'], (float) $r['est_max'])) ?></td>
<td class="text-nowrap"><?= $r['quoted_price'] !== null ? e(money($r['quoted_price'])) : '—' ?></td>
<td class="small"><?= $r['service_method'] === 'pickup' ? 'Pickup' : 'Drop off' ?></td>
<td><?= alteration_status_badge($r['status']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7"><div class="a-empty"><i class="bi bi-scissors"></i><p>No alteration requests yet.</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/alterations/')) ?>
<?php admin_footer();
