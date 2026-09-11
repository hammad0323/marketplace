<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$id = (int) ($_GET['id'] ?? 0);
$customer = tp_query_one('SELECT * FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$id, $bid]);
if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_note') {
    tp_require_csrf();
    $note = tp_sanitize_text($_POST['note'] ?? '', 2000);
    if ($note !== '') {
        tp_execute('INSERT INTO crm_notes (business_id, customer_id, note) VALUES (?,?,?)', 'iis', [$bid, $id, $note]);
        tp_flash_set('success', 'Note added.');
    }
    header('Location: ' . tp_url('crm/customer-view.php?id=' . $id));
    exit;
}

$balance = crm_customer_balance($bid, $id);
$notes = tp_query('SELECT * FROM crm_notes WHERE business_id=? AND customer_id=? ORDER BY created_at DESC', 'ii', [$bid, $id]);
$purchases = tp_query('SELECT * FROM crm_purchases WHERE business_id=? AND customer_id=? ORDER BY purchase_date DESC', 'ii', [$bid, $id]);
$ledger = tp_query('SELECT * FROM crm_ledger_entries WHERE business_id=? AND customer_id=? ORDER BY entry_date DESC, id DESC LIMIT 20', 'ii', [$bid, $id]);
$documents = tp_query('SELECT * FROM crm_documents WHERE business_id=? AND customer_id=? ORDER BY created_at DESC LIMIT 10', 'ii', [$bid, $id]);
$followups = tp_query('SELECT * FROM crm_followups WHERE business_id=? AND customer_id=? ORDER BY due_at DESC LIMIT 10', 'ii', [$bid, $id]);
$visits = tp_query('SELECT * FROM crm_visits WHERE business_id=? AND customer_id=? ORDER BY visit_at DESC LIMIT 10', 'ii', [$bid, $id]);

$crmPageTitle = $customer['name'];
$crmActive = 'customers';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1"><?= e($customer['name']) ?></h2>
    <p class="text-muted mb-0">
      <?= e($customer['phone']) ?><?= $customer['company'] ? ' · ' . e($customer['company']) : '' ?><?= $customer['city'] ? ' · ' . e($customer['city']) : '' ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($customer['phone']): ?><a href="<?= crm_whatsapp_link($customer['phone']) ?>" target="_blank" class="tp-btn tp-btn-sm" style="background:#25D366;color:#fff;"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif; ?>
    <a href="<?= tp_url('crm/document-form.php?customer_id=' . $id . '&doc_type=quotation') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;">New Quotation</a>
    <a href="<?= tp_url('crm/customer-form.php?id=' . $id) ?>" class="tp-btn tp-btn-sm tp-btn-light">Edit</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-value <?= $balance > 0 ? 'crm-balance-positive' : 'crm-balance-zero' ?>"><?= crm_currency_symbol() . number_format($balance, 0) ?></div>
      <div class="stat-label">Outstanding Balance</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-value"><?= count($documents) ?></div><div class="stat-label">Recent Documents</div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-value"><?= e($customer['source'] ?: '—') ?></div><div class="stat-label">Source</div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-value"><?= e($customer['business_id_number'] ?: '—') ?></div><div class="stat-label">CNIC / ID</div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="admin-card mb-3">
      <h3 class="h6 fw-bold mb-3">Contact History / Notes</h3>
      <form method="post" class="d-flex gap-2 mb-3">
        <?= tp_csrf_field() ?>
        <input type="hidden" name="action" value="add_note">
        <input type="text" name="note" class="form-control" placeholder="Add a note about this customer..." required>
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-plus-lg"></i></button>
      </form>
      <?php if (!$notes): ?><p class="text-muted small mb-0">No notes yet.</p><?php endif; ?>
      <?php foreach ($notes as $n): ?>
        <div class="border-bottom py-2 small">
          <div><?= nl2br(e($n['note'])) ?></div>
          <div class="text-muted"><?= date('M j, Y g:ia', strtotime($n['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-card mb-3">
      <h3 class="h6 fw-bold mb-3">Previous Purchases</h3>
      <?php if (!$purchases): ?><p class="text-muted small mb-0">No purchases logged yet.</p><?php endif; ?>
      <?php foreach ($purchases as $p): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span><?= e($p['description']) ?> <span class="text-muted">— <?= date('M j, Y', strtotime($p['purchase_date'])) ?></span></span>
          <strong><?= crm_currency_symbol() . number_format((float) $p['amount'], 0) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-card">
      <h3 class="h6 fw-bold mb-3">Field Visits</h3>
      <?php if (!$visits): ?><p class="text-muted small mb-0">No visits logged yet. <a href="<?= tp_url('crm/visit-form.php?customer_id=' . $id) ?>">Log one</a>.</p><?php endif; ?>
      <?php foreach ($visits as $v): ?>
        <div class="border-bottom py-2 small">
          <div><?= date('M j, Y g:ia', strtotime($v['visit_at'])) ?><?= $v['gps_lat'] ? ' · <a href="https://maps.google.com/maps?q=' . $v['gps_lat'] . ',' . $v['gps_lng'] . '" target="_blank">View on map</a>' : '' ?></div>
          <?php if ($v['notes']): ?><div class="text-muted"><?= e($v['notes']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="admin-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold m-0">Ledger (Khata)</h3>
        <a href="<?= tp_url('crm/ledger.php?customer_id=' . $id) ?>" class="small">Manage <i class="bi bi-arrow-right"></i></a>
      </div>
      <?php if (!$ledger): ?><p class="text-muted small mb-0">No transactions yet.</p><?php endif; ?>
      <?php foreach ($ledger as $l): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span><?= $l['entry_type'] === 'sale' ? '<i class="bi bi-cart text-danger"></i> Sale' : '<i class="bi bi-cash text-success"></i> Payment' ?><?= $l['note'] ? ' — ' . e($l['note']) : '' ?> <span class="text-muted">(<?= date('M j', strtotime($l['entry_date'])) ?>)</span></span>
          <strong><?= crm_currency_symbol() . number_format((float) $l['amount'], 0) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold m-0">Documents</h3>
        <a href="<?= tp_url('crm/document-form.php?customer_id=' . $id) ?>" class="small">New <i class="bi bi-plus-lg"></i></a>
      </div>
      <?php if (!$documents): ?><p class="text-muted small mb-0">No documents yet.</p><?php endif; ?>
      <?php foreach ($documents as $d): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <a href="<?= tp_url('crm/document-view.php?id=' . $d['id']) ?>"><?= crm_doc_type_label($d['doc_type']) ?> <?= e($d['doc_number']) ?></a>
          <strong><?= crm_currency_symbol() . number_format((float) $d['total'], 0) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold m-0">Follow-ups</h3>
        <a href="<?= tp_url('crm/followup-form.php?customer_id=' . $id) ?>" class="small">New <i class="bi bi-plus-lg"></i></a>
      </div>
      <?php if (!$followups): ?><p class="text-muted small mb-0">No follow-ups yet.</p><?php endif; ?>
      <?php foreach ($followups as $f): ?>
        <div class="border-bottom py-2 small">
          <div><?= e($f['note']) ?> <?= $f['completed_at'] ? '<span class="badge bg-success">Done</span>' : '' ?></div>
          <div class="text-muted">Due <?= date('M j, Y g:ia', strtotime($f['due_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
