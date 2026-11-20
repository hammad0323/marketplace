<?php
require __DIR__ . '/_init.php';
$q = trim((string) ($_GET['q'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));
$state = (string) ($_GET['state'] ?? '');
$where = ['s.deleted_at IS NULL'];
$params = [];
if ($q !== '') {
    $where[] = '(s.name LIKE ? OR s.shop_code LIKE ? OR s.owner_name LIKE ? OR s.phone LIKE ? OR u.username LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
if ($city !== '') {
    $where[] = 's.city = ?';
    $params[] = $city;
}
$rows = db_all('SELECT s.*, u.username, (SELECT COUNT(*) FROM orders o WHERE o.shop_id = s.id) orders FROM shops s LEFT JOIN shop_users u ON u.shop_id = s.id AND u.role = \'owner\'
    WHERE ' . implode(' AND ', $where) . ' GROUP BY s.id ORDER BY s.id DESC', $params);
foreach ($rows as $k => &$r) {
    $r['sub'] = subscription_state((int) $r['id'], $r);
    $st = $r['sub']['state'];
    if ($state !== '' && !($state === 'expired' ? in_array($st, ['expired', 'none'], true) : $st === $state)) {
        unset($rows[$k]);
    }
}
unset($r);
$cities = array_column(db_all('SELECT DISTINCT city FROM shops WHERE deleted_at IS NULL ORDER BY city'), 'city');
admin_header('Print shops');
?>
<div class="d-flex flex-wrap gap-2 align-items-start">
<form class="p-filters" method="get">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, shop ID, owner, phone, username">
    <select class="form-select" name="city"><option value="">All cities</option><?php foreach ($cities as $c): ?><option<?= $c === $city ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="state"><option value="">Any subscription</option><?php foreach (['active' => 'Active', 'grace' => 'Grace period', 'expired' => 'Expired / none', 'suspended' => 'Suspended'] as $k => $l): ?><option value="<?= $k ?>"<?= $state === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline-primary">Filter</button>
</form>
<a class="btn btn-primary ms-auto" href="<?= e(path('admin/shop-form.php')) ?>"><i class="bi bi-plus-lg"></i> Add shop</a>
</div>
<div class="p-card"><div class="table-responsive"><table class="table p-table">
<thead><tr><th>Shop</th><th>Shop ID</th><th>Owner / login</th><th>City</th><th>Subscription</th><th>Price</th><th>Orders</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><div class="d-flex align-items-center gap-2"><?php if ($r['logo_path']): ?><img class="p-thumb" src="<?= e(path('media/' . $r['logo_path'])) ?>" alt=""><?php else: ?><span class="p-thumb d-inline-flex align-items-center justify-content-center"><i class="bi bi-shop text-muted"></i></span><?php endif; ?>
    <div><a href="<?= e(path('admin/shop.php?id=' . $r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a><div class="small text-muted"><?= e($r['phone']) ?></div></div></div></td>
<td><code><?= e($r['shop_code']) ?></code></td><td><?= e($r['owner_name']) ?><div class="small text-muted"><?= e($r['username']) ?></div></td><td><?= e($r['city']) ?></td>
<td><?= sub_badge($r['sub']['state']) ?><div class="small text-muted"><?= $r['sub']['end_date'] ? 'until ' . e(format_date($r['sub']['end_date'])) : '' ?></div></td>
<td class="text-nowrap"><?= e(money($r['sub']['price'])) ?><?= $r['subscription_price'] !== null ? ' <span class="p-chip">custom</span>' : '' ?></td>
<td><?= (int) $r['orders'] ?></td><td><span class="badge rounded-pill text-bg-<?= ['active' => 'success', 'suspended' => 'warning', 'deactivated' => 'secondary'][$r['status']] ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8"><div class="p-empty"><i class="bi bi-shop"></i>No shops found.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_footer();
