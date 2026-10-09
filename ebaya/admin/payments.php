<?php
/** Payment transaction log & reconciliation. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('payments.manage');
$w = ['1=1']; $p = [];
if (($g = get('gateway')) && preg_match('/^[a-z]+$/', $g)) { $w[] = 't.gateway = ?'; $p[] = $g; }
if (($s = get('ref')) !== '') { $w[] = '(t.reference LIKE ? OR o.order_number LIKE ?)'; $p[] = "%$s%"; $p[] = "%$s%"; }
$ws = implode(' AND ', $w);
$pg = paginate((int)db_val("SELECT COUNT(*) FROM payment_transactions t LEFT JOIN orders o ON o.id = t.order_id WHERE $ws", $p), 50, (int)get('page', 1));
$rows = db_all("SELECT t.*, o.order_number FROM payment_transactions t LEFT JOIN orders o ON o.id = t.order_id WHERE $ws ORDER BY t.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $p);
$recon = db_all("SELECT method, status, COUNT(*) n, SUM(amount) amt FROM payments GROUP BY method, status ORDER BY method, status");
$view = get('view') ? db_one('SELECT * FROM payment_transactions WHERE id = ?', [(int)get('view')]) : null;
$admin_title = 'Payment log';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <form class="d-flex gap-2 mb-2"><select name="gateway" class="form-select form-select-sm" style="width:160px"><option value="">All gateways</option><?php foreach (['cod', 'jazzcash', 'easypaisa', 'card'] as $x): ?><option<?= get('gateway') === $x ? ' selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select>
      <input class="form-control form-control-sm" name="ref" value="<?= e(get('ref')) ?>" placeholder="Reference or order #" style="width:220px"><button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>Time</th><th>Order</th><th>Gateway</th><th>Event</th><th>Status</th><th>Reference</th><th class="text-end">Amount</th><th>Sig.</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $t): ?><tr><td class="small"><?= e($t['created_at']) ?></td><td><?= $t['order_id'] ? '<a href="' . e(admin_url('order-view?id=' . $t['order_id'])) . '">' . e($t['order_number']) . '</a>' : '—' ?></td><td><?= e($t['gateway']) ?></td><td><?= e($t['event_type']) ?></td><td><?= e($t['status']) ?></td><td class="small"><?= e($t['reference']) ?></td><td class="text-end"><?= $t['amount'] !== null ? money($t['amount']) : '' ?></td>
        <td><?= $t['signature_valid'] === null ? '—' : ($t['signature_valid'] ? '✓' : '<span class="text-danger">✗</span>') ?></td><td><a class="small" href="<?= e(query_with(['view' => $t['id']])) ?>">Details</a></td></tr><?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="text-muted text-center py-3">No transactions logged yet.</td></tr><?php endif; ?></tbody>
    </table></div></div>
    <div class="mt-2"><?= admin_pager($pg) ?></div>
  </div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header">Reconciliation (payment attempts)</div><table class="table table-sm mb-0"><tbody>
      <?php foreach ($recon as $r): ?><tr><td><?= e($r['method']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= (int)$r['n'] ?></td><td class="text-end"><?= money($r['amt']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($view): ?><div class="card"><div class="card-header">Transaction #<?= (int)$view['id'] ?></div><div class="card-body small">
      <p><?= e($view['message']) ?></p><pre class="bg-light p-2 small" style="white-space:pre-wrap;max-height:400px;overflow:auto"><?= e(json_encode(json_decode((string)$view['payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
      <p class="text-muted mb-0">Secrets and secure hashes are redacted before logging. Card data is never received by this server.</p></div></div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
