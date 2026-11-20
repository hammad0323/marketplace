<?php
require __DIR__ . '/_init.php';
$filter = (string) ($_GET['state'] ?? '');
$rows = [];
foreach (db_all('SELECT * FROM shops WHERE deleted_at IS NULL AND status <> \'deactivated\' ORDER BY name') as $s) {
    $st = subscription_state((int) $s['id'], $s);
    if ($filter && !($filter === 'expired' ? in_array($st['state'], ['expired', 'none'], true) : $st['state'] === $filter)) {
        continue;
    }
    if ($filter === 'soon' && !($st['days_left'] !== null && $st['days_left'] >= 0 && $st['days_left'] <= 7)) {
        continue;
    }
    $rows[] = ['shop' => $s, 'st' => $st];
}
usort($rows, fn($a, $b) => ($a['st']['days_left'] ?? -9999) <=> ($b['st']['days_left'] ?? -9999));
$mrr = array_sum(array_map(fn($r) => in_array($r['st']['state'], ['active', 'grace'], true) ? $r['st']['price'] : 0, $rows));
admin_header('Subscriptions');
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group">
        <?php foreach (['' => 'All', 'active' => 'Active', 'grace' => 'Grace period', 'expired' => 'Expired', 'suspended' => 'Suspended'] as $k => $l): ?><a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline-primary' ?>" href="?state=<?= e($k) ?>"><?= e($l) ?></a><?php endforeach; ?>
    </div>
    <span class="ms-auto text-muted small">Default price <strong><?= e(money(subscription_default_price())) ?></strong>/month · <a href="<?= e(path('admin/settings.php')) ?>">change</a> · MRR shown: <strong><?= e(money($mrr)) ?></strong></span>
</div>
<div class="p-card"><div class="table-responsive"><table class="table p-table">
<thead><tr><th>Shop</th><th>Status</th><th>Current period</th><th>Days left</th><th>Price / month</th><th>Last payment</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $s = $r['shop']; $st = $r['st']; $last = db_row("SELECT amount, paid_on, method FROM subscription_payments WHERE shop_id = ? AND status = 'confirmed' ORDER BY paid_on DESC, id DESC LIMIT 1", [(int) $s['id']]); ?>
<tr><td><a href="<?= e(path('admin/shop.php?id=' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><div class="small text-muted"><code><?= e($s['shop_code']) ?></code> · <?= e($s['city']) ?></div></td>
<td><?= sub_badge($st['state']) ?></td>
<td class="small"><?= $st['subscription'] ? e(format_date($st['subscription']['start_date'])) . ' – ' . e(format_date($st['end_date'])) : '—' ?></td>
<td><?= $st['days_left'] === null ? '—' : ($st['days_left'] >= 0 ? '<strong' . ($st['days_left'] <= 5 ? ' class="text-warning"' : '') . '>' . $st['days_left'] . '</strong>' : '<span class="text-danger">' . $st['days_left'] . '</span>') ?></td>
<td><?= e(money($st['price'])) ?><?= $s['subscription_price'] !== null ? ' <span class="p-chip">custom</span>' : '' ?></td>
<td class="small"><?= $last ? e(money($last['amount'])) . ' · ' . e(format_date($last['paid_on'])) . ' · ' . e(payment_methods()[$last['method']]) : '<span class="text-muted">—</span>' ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(path('admin/payments.php?record=' . $s['id'])) ?>">Record payment</a></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7"><div class="p-empty"><i class="bi bi-calendar"></i>Nothing here.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_footer();
