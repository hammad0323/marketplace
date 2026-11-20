<?php
/** Design Studio: quote requests and recent designs ordered straight from the studio. */
require __DIR__ . '/../includes/init.php';
admin_require('design_studio');
$status = input('status', '', 'get');
$q = input('q', '', 'get');
$where = ['1'];
$params = [];
if (isset(design_request_statuses()[$status])) { $where[] = 'r.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(r.reference LIKE ? OR r.name LIKE ? OR r.phone LIKE ? OR r.city LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM design_requests r WHERE $w", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT r.*, d.preview_front, d.color, p.name product_name FROM design_requests r JOIN designs d ON d.id = r.design_id LEFT JOIN products p ON p.id = d.product_id
    WHERE $w ORDER BY r.status = 'new' DESC, r.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$counts = array_column(db_all('SELECT status, COUNT(*) n FROM design_requests GROUP BY status'), 'n', 'status');
$ordered = db_all("SELECT oi.order_id, o.order_number, o.created_at, oi.qty, oi.variant_label, oi.custom_spec, oi.image FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE oi.is_custom = 2 ORDER BY oi.id DESC LIMIT 12");
admin_header('Design requests');
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form class="a-filters mb-0" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Reference, name, phone, city">
        <select class="form-select" name="status"><option value="">All statuses</option><?php foreach (design_request_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</option><?php endforeach; ?></select>
        <button class="btn btn-dark">Filter</button></form>
    <a class="btn btn-outline-dark ms-auto" href="<?= e(url('admin/designs/settings.php')) ?>"><i class="bi bi-cash-coin"></i> Prices &amp; settings</a>
    <a class="btn btn-outline-dark" href="<?= e(url('design-studio')) ?>" target="_blank"><i class="bi bi-eye"></i> Design Studio</a>
</div>
<p class="text-muted small">Pricing mode: <strong><?= design_mode() === 'quote' ? 'Quote — you confirm each price here' : 'Automatic — customers see the price and order straight away' ?></strong>. Quote requests appear below; designs ordered directly are listed at the bottom.</p>
<div class="card a-card"><div class="table-responsive"><table class="table a-table align-middle mb-0">
<thead><tr><th></th><th>Reference</th><th>Customer</th><th>Garment</th><th>Pieces</th><th>Print estimate</th><th>Confirmed print</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><div class="a-thumb a-thumb-sm"><?php if ($r['preview_front']): ?><img src="<?= e(img_url($r['preview_front'])) ?>" alt=""><?php endif; ?></div></td>
<td><a href="<?= e(url('admin/designs/view.php?id=' . $r['id'])) ?>"><strong><?= e($r['reference']) ?></strong></a><div class="small text-muted"><?= e(format_datetime($r['created_at'])) ?></div></td>
<td><?= e($r['name']) ?><div class="small text-muted"><?= e($r['phone']) ?> · <?= e($r['city']) ?></div></td>
<td class="small"><?= e($r['product_name']) ?><div class="text-muted"><?= e($r['color']) ?></div></td>
<td><?= (int) $r['qty'] ?><div class="small text-muted"><?= e(implode(', ', array_map(fn($s, $n) => $s . '×' . $n, array_keys(json_decode($r['sizes'], true) ?: []), json_decode($r['sizes'], true) ?: []))) ?></div></td>
<td class="text-nowrap"><?= e(money($r['estimate_print'])) ?> <small class="text-muted">/pc</small></td>
<td class="text-nowrap"><?= $r['quoted_print_price'] !== null ? e(money($r['quoted_print_price'])) . ' <small class="text-muted">/pc</small>' : '—' ?></td>
<td><?= design_request_badge($r['status']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8"><div class="a-empty"><i class="bi bi-palette2"></i><p>No design requests yet.</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/designs/')) ?>

<div class="card a-card mt-4"><div class="card-header">Recently ordered designs</div><div class="table-responsive"><table class="table a-table align-middle mb-0">
<thead><tr><th></th><th>Order</th><th>Item</th><th>Qty</th><th></th></tr></thead><tbody>
<?php foreach ($ordered as $o): $spec = json_decode((string) $o['custom_spec'], true) ?: []; ?>
<tr><td><div class="a-thumb a-thumb-sm"><?php if ($o['image']): ?><img src="<?= e(img_url($o['image'])) ?>" alt=""><?php endif; ?></div></td>
<td><a href="<?= e(url('admin/orders/view.php?id=' . $o['order_id'])) ?>"><?= e($o['order_number']) ?></a><div class="small text-muted"><?= e(format_datetime($o['created_at'])) ?></div></td>
<td class="small"><?= e($o['variant_label']) ?></td><td><?= (int) $o['qty'] ?></td>
<td><?php if (!empty($spec['design'])): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/designs/design.php?token=' . $spec['design'])) ?>"><i class="bi bi-printer"></i> Print sheet</a><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$ordered): ?><tr><td colspan="5" class="text-muted small">No designs ordered yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_footer();
