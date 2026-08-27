<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['manager', 'employee']);

$q = get_param('q', '');
if (mb_strlen($q) < 2) {
    json_response(['success' => true, 'items' => []]);
}
$companyId = require_company_id();
$like = '%' . db_escape_like($q) . '%';
$items = [];

foreach (db_all("SELECT id, name FROM users WHERE company_id=? AND name LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'Employee', 'label' => $r['name'], 'url' => base_url('manager/employees.php?highlight=' . $r['id'])];
}
foreach (db_all("SELECT id, name FROM products WHERE company_id=? AND name LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'Product', 'label' => $r['name'], 'url' => base_url('manager/products.php?highlight=' . $r['id'])];
}
foreach (db_all("SELECT id, batch_number FROM batches WHERE company_id=? AND batch_number LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'Batch', 'label' => $r['batch_number'], 'url' => base_url('manager/traceability.php?batch=' . urlencode($r['batch_number']))];
}
foreach (db_all("SELECT id, issue_number, description FROM quality_issues WHERE company_id=? AND (issue_number LIKE ? OR description LIKE ?) LIMIT 5", [$companyId, $like, $like]) as $r) {
    $items[] = ['type' => 'Issue', 'label' => $r['issue_number'], 'url' => base_url('manager/issue-view.php?id=' . $r['id'])];
}
foreach (db_all("SELECT id, ncr_number FROM ncr WHERE company_id=? AND ncr_number LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'NCR', 'label' => $r['ncr_number'], 'url' => base_url('manager/ncr-view.php?id=' . $r['id'])];
}
foreach (db_all("SELECT id, capa_number FROM capa WHERE company_id=? AND capa_number LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'CAPA', 'label' => $r['capa_number'], 'url' => base_url('manager/capa-view.php?id=' . $r['id'])];
}
foreach (db_all("SELECT id, name FROM suppliers WHERE company_id=? AND name LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'Supplier', 'label' => $r['name'], 'url' => base_url('manager/suppliers.php?highlight=' . $r['id'])];
}
foreach (db_all("SELECT id, complaint_number FROM customer_complaints WHERE company_id=? AND complaint_number LIKE ? LIMIT 5", [$companyId, $like]) as $r) {
    $items[] = ['type' => 'Complaint', 'label' => $r['complaint_number'], 'url' => base_url('manager/complaints.php?highlight=' . $r['id'])];
}

json_response(['success' => true, 'items' => array_slice($items, 0, 20)]);
