<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$typeFilter = $_GET['type'] ?? '';
$where = 'business_id = ?';
$types = 'i';
$params = [$bid];
if (isset(CRM_DOC_TYPES[$typeFilter])) {
    $where .= ' AND doc_type = ?';
    $types .= 's';
    $params[] = $typeFilter;
}

$documents = tp_query("SELECT * FROM crm_documents WHERE $where ORDER BY created_at DESC LIMIT 200", $types, $params);

$crmPageTitle = 'Sales Documents';
$crmActive = 'documents';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="get" class="d-flex gap-2">
    <select name="type" class="form-select" onchange="this.form.submit()">
      <option value="">All types</option>
      <?php foreach (CRM_DOC_TYPES as $key => $info): ?>
        <option value="<?= $key ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a href="<?= tp_url('crm/document-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> New Document</a>
</div>

<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Number</th><th>Type</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($documents as $d): ?>
      <tr>
        <td><?= e($d['doc_number']) ?></td>
        <td><?= crm_doc_type_label($d['doc_type']) ?></td>
        <td><?= e($d['customer_name']) ?></td>
        <td><?= crm_currency_symbol() . number_format((float) $d['total'], 2) ?></td>
        <td><span class="badge bg-<?= $d['status'] === 'paid' ? 'success' : ($d['status'] === 'cancelled' ? 'secondary' : 'primary') ?>"><?= e($d['status']) ?></span></td>
        <td><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
        <td><a href="<?= tp_url('crm/document-view.php?id=' . $d['id']) ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$documents): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No documents yet. <a href="<?= tp_url('crm/document-form.php') ?>">Create your first one</a>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
