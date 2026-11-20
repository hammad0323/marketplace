<?php
require __DIR__ . '/_init.php';
$status = (string) ($_GET['status'] ?? 'new');
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$list = owner_order_list(owner_shop_id(), $status === 'all' ? '' : $status, $q, $page, 25);
$tabs = ['new' => 'New', 'active' => 'In progress', 'ready' => 'Ready', 'completed' => 'Completed', 'closed' => 'Rejected / cancelled', 'all' => 'All'];
owner_header('Orders');
?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
<ul class="nav nav-pills gap-1"><?php foreach ($tabs as $k => $l): ?><li class="nav-item"><a class="nav-link py-1 px-3<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
<form class="ms-auto d-flex gap-2" method="get"><input type="hidden" name="status" value="<?= e($status) ?>"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Order #, name or phone"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button></form>
</div>
<div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Order</th><th>Customer</th><th>What</th><th>Status</th><th class="text-end">Amount</th><th>Received</th></tr></thead><tbody>
<?php foreach ($list['items'] as $o): ?>
<tr style="cursor:pointer" onclick="location.href='<?= e(path('owner/order.php?id=' . $o['id'])) ?>'"><td><a href="<?= e(path('owner/order.php?id=' . $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><div class="small text-muted"><?= $o['source'] === 'app' ? 'App' : 'Web' ?></div></td>
<td><?= e($o['customer_name']) ?><div class="small text-muted"><?= e($o['customer_phone']) ?></div></td><td class="small"><?= e($o['categories'] ?: '—') ?><div class="text-muted"><?= (int) $o['items'] ?> item<?= $o['items'] > 1 ? 's' : '' ?></div></td>
<td><?= order_badge($o['status']) ?><?= $o['payment_status'] === 'pending_verification' ? '<div class="small text-warning mt-1"><i class="bi bi-cash"></i> Payment to verify</div>' : '' ?></td>
<td class="text-end"><?= $o['amount'] !== null ? e(money($o['amount'])) : '<span class="badge text-bg-primary">Needs price</span>' ?></td><td class="small text-muted"><?= e(time_ago($o['created_at'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$list['items']): ?><tr><td colspan="6"><div class="p-empty"><i class="bi bi-inbox"></i>No orders here.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php if ($list['pages'] > 1): ?><nav><ul class="pagination pagination-sm"><?php for ($i = 1; $i <= $list['pages']; $i++): ?><li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="?status=<?= e($status) ?>&q=<?= e(urlencode($q)) ?>&page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<script>document.addEventListener('DOMContentLoaded', function () { jQuery(document).on('ph:notifications', function (e, items) { if (items.some(function (n) { return n.type === 'new_order' || n.type === 'payment_submitted' || n.type === 'quote_accepted'; })) { location.reload(); } }); });</script>
<?php owner_footer();
