<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? tp_query_one('SELECT * FROM crm_leads WHERE id = ? AND business_id = ?', 'ii', [$id, $bid]) : null;
if ($id && !$lead) {
    http_response_code(404);
    exit('Lead not found.');
}

$customers = tp_query('SELECT id, name, phone, company FROM crm_customers WHERE business_id = ? ORDER BY name', 'i', [$bid]);

$error = null;
$old = $lead ?? [
    'customer_id' => (int) ($_GET['customer_id'] ?? 0), 'name' => '', 'phone' => '', 'company' => '',
    'source' => '', 'status' => 'new', 'expected_value' => 0, 'next_followup_at' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $old['customer_id'] = $customerId;
    $old['name'] = tp_sanitize_text($_POST['name'] ?? '', 160);
    $old['phone'] = tp_sanitize_text($_POST['phone'] ?? '', 30);
    $old['company'] = tp_sanitize_text($_POST['company'] ?? '', 160);
    $old['source'] = tp_sanitize_text($_POST['source'] ?? '', 80);
    $old['status'] = $_POST['status'] ?? 'new';
    $old['expected_value'] = (float) tp_sanitize_number($_POST['expected_value'] ?? 0);
    $followup = tp_sanitize_text($_POST['next_followup_at'] ?? '', 30);
    $old['next_followup_at'] = $followup;

    if ($old['name'] === '') {
        $error = 'Lead name is required.';
    } elseif (!isset(CRM_PIPELINE_STAGES[$old['status']])) {
        $error = 'Invalid pipeline stage.';
    } else {
        $followupSql = $followup !== '' ? date('Y-m-d H:i:s', strtotime($followup)) : null;
        if ($lead) {
            tp_execute(
                'UPDATE crm_leads SET customer_id=?, name=?, phone=?, company=?, source=?, status=?, expected_value=?, next_followup_at=? WHERE id=? AND business_id=?',
                'isssssdsii',
                [$customerId, $old['name'], $old['phone'], $old['company'], $old['source'], $old['status'], $old['expected_value'], $followupSql, $id, $bid]
            );
            tp_flash_set('success', 'Lead updated.');
        } else {
            $result = tp_execute(
                'INSERT INTO crm_leads (business_id, customer_id, name, phone, company, source, status, expected_value, next_followup_at) VALUES (?,?,?,?,?,?,?,?,?)',
                'iisssssds',
                [$bid, $customerId, $old['name'], $old['phone'], $old['company'], $old['source'], $old['status'], $old['expected_value'], $followupSql]
            );
            $id = $result['insert_id'];
            tp_flash_set('success', 'Lead added.');
        }
        header('Location: ' . tp_url('crm/leads.php'));
        exit;
    }
}

$crmPageTitle = $lead ? 'Edit Lead' : 'Add Lead';
$crmActive = 'leads';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card" style="max-width:640px;">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= tp_csrf_field() ?>
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Link to Existing Customer <span class="text-muted small">(optional)</span></label>
        <select name="customer_id" class="form-select" id="customerSelect">
          <option value="0">— Not linked / new prospect —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" data-phone="<?= e($c['phone']) ?>" data-company="<?= e($c['company']) ?>" <?= (int) $old['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label">Lead Name *</label><input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required autofocus></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="leadPhone" class="form-control" value="<?= e($old['phone']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company" id="leadCompany" class="form-control" value="<?= e($old['company']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Source</label><input type="text" name="source" class="form-control" value="<?= e($old['source']) ?>" placeholder="e.g. Referral, Facebook"></div>
      <div class="col-md-6">
        <label class="form-label">Stage</label>
        <select name="status" class="form-select">
          <?php foreach (CRM_PIPELINE_STAGES as $key => $label): ?>
            <option value="<?= $key ?>" <?= $old['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label">Expected Value</label><input type="number" step="0.01" min="0" name="expected_value" class="form-control" value="<?= e((string) $old['expected_value']) ?>"></div>
      <div class="col-12"><label class="form-label">Next Follow-up</label><input type="datetime-local" name="next_followup_at" class="form-control" value="<?= $old['next_followup_at'] ? date('Y-m-d\TH:i', strtotime($old['next_followup_at'])) : '' ?>"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit"><?= $lead ? 'Save Changes' : 'Add Lead' ?></button>
      <a href="<?= tp_url('crm/leads.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>
<script>
document.getElementById('customerSelect').addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  if (this.value !== '0') {
    document.getElementById('leadPhone').value = opt.dataset.phone || '';
    document.getElementById('leadCompany').value = opt.dataset.company || '';
  }
});
</script>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
