<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$customerId = (int) ($_GET['customer_id'] ?? 0);
$customer = $customerId ? tp_query_one('SELECT * FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$customerId, $bid]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_entry') {
    tp_require_csrf();
    $cid = (int) ($_POST['customer_id'] ?? 0);
    $entryType = ($_POST['entry_type'] ?? '') === 'payment' ? 'payment' : 'sale';
    $amount = (float) tp_sanitize_number($_POST['amount'] ?? 0);
    $entryDate = tp_sanitize_text($_POST['entry_date'] ?? date('Y-m-d'), 10);
    $note = tp_sanitize_text($_POST['note'] ?? '', 255);

    $belongs = tp_query_one('SELECT id FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$cid, $bid]);
    if ($belongs && $amount > 0) {
        tp_execute(
            'INSERT INTO crm_ledger_entries (business_id, customer_id, entry_type, amount, entry_date, note) VALUES (?,?,?,?,?,?)',
            'iisdss',
            [$bid, $cid, $entryType, $amount, $entryDate, $note]
        );
        tp_flash_set('success', ucfirst($entryType) . ' of ' . crm_currency_symbol() . number_format($amount, 2) . ' recorded.');
    }
    header('Location: ' . tp_url('crm/ledger.php?customer_id=' . $cid));
    exit;
}

if ($customer) {
    $entries = tp_query('SELECT * FROM crm_ledger_entries WHERE business_id = ? AND customer_id = ? ORDER BY entry_date DESC, id DESC', 'ii', [$bid, $customerId]);
    $balance = crm_customer_balance($bid, $customerId);
} else {
    // Receivables aging summary across all customers with an outstanding balance
    $aging = tp_query(
        "SELECT cu.id, cu.name, cu.phone,
                COALESCE(SUM(CASE WHEN le.entry_type='sale' THEN le.amount ELSE -le.amount END),0) balance,
                MAX(le.entry_date) last_entry
         FROM crm_customers cu
         LEFT JOIN crm_ledger_entries le ON le.customer_id = cu.id AND le.business_id = cu.business_id
         WHERE cu.business_id = ?
         GROUP BY cu.id
         HAVING balance > 0
         ORDER BY balance DESC",
        'i',
        [$bid]
    );
}

$crmPageTitle = $customer ? 'Ledger — ' . $customer['name'] : 'Payments / Khata';
$crmActive = 'ledger';
require __DIR__ . '/includes/crm-header.php';
?>
<?php if ($customer): ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="h5 fw-bold mb-0"><?= e($customer['name']) ?></h2>
      <span class="<?= $balance > 0 ? 'crm-balance-positive' : 'crm-balance-zero' ?>">Balance owed: <?= crm_currency_symbol() . number_format($balance, 2) ?></span>
    </div>
    <a href="<?= tp_url('crm/ledger.php') ?>" class="tp-btn tp-btn-sm tp-btn-light">All Customers</a>
  </div>

  <div class="row g-3">
    <div class="col-md-5">
      <div class="admin-card">
        <h3 class="h6 fw-bold mb-3">Record a Transaction</h3>
        <form method="post">
          <?= tp_csrf_field() ?>
          <input type="hidden" name="action" value="add_entry">
          <input type="hidden" name="customer_id" value="<?= $customerId ?>">
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="entry_type" class="form-select">
              <option value="sale">Sale (increases balance owed)</option>
              <option value="payment">Payment Received (reduces balance)</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="mb-3"><label class="form-label">Note</label><input type="text" name="note" class="form-control" placeholder="e.g. Cash, Easypaisa, Bank transfer"></div>
          <button class="tp-btn w-100" style="background:var(--tp-indigo);color:#fff;" type="submit">Save Entry</button>
        </form>
      </div>
    </div>
    <div class="col-md-7">
      <div class="admin-card">
        <h3 class="h6 fw-bold mb-3">Transaction History</h3>
        <table class="table table-sm">
          <thead><tr><th>Date</th><th>Type</th><th>Note</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= date('M j, Y', strtotime($e['entry_date'])) ?></td>
              <td><?= $e['entry_type'] === 'sale' ? '<span class="text-danger">Sale</span>' : '<span class="text-success">Payment</span>' ?></td>
              <td><?= e($e['note']) ?></td>
              <td class="text-end"><?= crm_currency_symbol() . number_format((float) $e['amount'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$entries): ?><tr><td colspan="4" class="text-center text-muted py-3">No transactions yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php else: ?>
  <p class="text-muted small mb-3">Customers with an outstanding balance, sorted by receivables aging (oldest activity first among unpaid balances).</p>
  <div class="admin-card">
    <table class="table tp-datatable">
      <thead><tr><th>Customer</th><th>Phone</th><th>Balance Owed</th><th>Last Activity</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($aging as $row):
          $daysSince = $row['last_entry'] ? (int) floor((time() - strtotime($row['last_entry'])) / 86400) : null;
        ?>
        <tr>
          <td><a href="<?= tp_url('crm/ledger.php?customer_id=' . $row['id']) ?>"><?= e($row['name']) ?></a></td>
          <td><?= e($row['phone']) ?></td>
          <td class="crm-balance-positive"><?= crm_currency_symbol() . number_format((float) $row['balance'], 2) ?></td>
          <td><?= $daysSince !== null ? $daysSince . ' day(s) ago' : '—' ?></td>
          <td>
            <a href="<?= tp_url('crm/ledger.php?customer_id=' . $row['id']) ?>" class="btn btn-sm btn-outline-secondary">Manage</a>
            <?php if ($row['phone']): ?>
              <a href="<?= crm_whatsapp_link($row['phone'], 'Dear ' . $row['name'] . ', a friendly reminder that your outstanding balance is ' . crm_currency_symbol() . number_format((float) $row['balance'], 2) . '. Please arrange payment at your earliest convenience. Thank you!') ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> Remind</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$aging): ?><tr><td colspan="5" class="text-center text-muted py-4">No outstanding balances. Everyone's paid up!</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
