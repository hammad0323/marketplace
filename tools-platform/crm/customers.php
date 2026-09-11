<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$q = trim((string) ($_GET['q'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));

$where = 'business_id = ?';
$types = 'i';
$params = [$bid];

if ($q !== '') {
    $where .= ' AND (name LIKE ? OR phone LIKE ? OR company LIKE ? OR business_id_number LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($city !== '') {
    $where .= ' AND city = ?';
    $types .= 's';
    $params[] = $city;
}

$customers = tp_query(
    "SELECT cu.*, (SELECT COALESCE(SUM(CASE WHEN le.entry_type='sale' THEN le.amount ELSE -le.amount END),0)
       FROM crm_ledger_entries le WHERE le.customer_id = cu.id AND le.business_id = cu.business_id) balance
     FROM crm_customers cu WHERE $where ORDER BY cu.created_at DESC",
    $types,
    $params
);

$cities = tp_query('SELECT DISTINCT city FROM crm_customers WHERE business_id = ? AND city IS NOT NULL AND city <> "" ORDER BY city', 'i', [$bid]);

$crmPageTitle = 'Customers';
$crmActive = 'customers';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap">
    <input type="text" name="q" class="form-control" style="max-width:280px;" placeholder="Search name, phone, company, ID..." value="<?= e($q) ?>">
    <select name="city" class="form-select" style="max-width:180px;">
      <option value="">All cities</option>
      <?php foreach ($cities as $c): ?>
        <option value="<?= e($c['city']) ?>" <?= $city === $c['city'] ? 'selected' : '' ?>><?= e($c['city']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
  </form>
  <a href="<?= tp_url('crm/customer-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> Add Customer</a>
</div>

<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Name</th><th>Phone</th><th>Company</th><th>City</th><th>Balance</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): $balance = (float) $c['balance']; ?>
      <tr>
        <td><a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a></td>
        <td><?= e($c['phone']) ?></td>
        <td><?= e($c['company']) ?></td>
        <td><?= e($c['city']) ?></td>
        <td class="<?= $balance > 0 ? 'crm-balance-positive' : 'crm-balance-zero' ?>"><?= crm_currency_symbol() . number_format($balance, 0) ?></td>
        <td>
          <a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">View</a>
          <a href="<?= tp_url('crm/customer-form.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <?php if ($c['phone']): ?>
            <a href="<?= crm_whatsapp_link($c['phone']) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No customers yet. <a href="<?= tp_url('crm/customer-form.php') ?>">Add your first one</a>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
