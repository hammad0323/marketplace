<?php
require __DIR__ . '/_init.php';
$type = (string) ($_GET['actor'] ?? '');
$where = '1';
$params = [];
if (in_array($type, ['admin', 'owner', 'customer', 'system'], true)) { $where = 'l.actor_type = ?'; $params[] = $type; }
$total = (int) db_val("SELECT COUNT(*) FROM activity_logs l WHERE $where", $params);
$pg = paginate($total, 50);
$rows = db_all("SELECT l.*, s.name shop_name FROM activity_logs l LEFT JOIN shops s ON s.id = l.shop_id WHERE $where ORDER BY l.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Activity log');
?>
<div class="btn-group mb-3"><?php foreach (['' => 'All', 'admin' => 'Admins', 'owner' => 'Shop owners', 'customer' => 'Customers', 'system' => 'System'] as $k => $l): ?><a class="btn btn-sm <?= $type === $k ? 'btn-primary' : 'btn-outline-primary' ?>" href="?actor=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Shop</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $l): ?>
<tr><td class="small text-nowrap"><?= e(format_datetime($l['created_at'])) ?></td><td class="small"><?= e($l['actor_type']) ?><?= $l['actor_id'] ? ' #' . (int) $l['actor_id'] : '' ?></td>
<td><?= e(str_replace('_', ' ', $l['action'])) ?><?= $l['entity_type'] ? ' <span class="text-muted small">' . e($l['entity_type']) . ($l['entity_id'] ? ' #' . (int) $l['entity_id'] : '') . '</span>' : '' ?></td>
<td class="small"><?= $l['shop_id'] ? '<a href="' . e(path('admin/shop.php?id=' . $l['shop_id'])) . '">' . e($l['shop_name'] ?? '#' . $l['shop_id']) . '</a>' : '—' ?></td>
<td class="small text-muted" style="max-width:260px"><?= e(str_limit($l['details'], 100)) ?></td><td class="small text-muted"><?= e($l['ip_address']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?= pagination_html($pg) ?>
<?php admin_footer();
