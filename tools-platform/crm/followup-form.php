<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$leads = tp_query('SELECT id, name FROM crm_leads WHERE business_id = ? ORDER BY name', 'i', [$bid]);
$customers = tp_query('SELECT id, name FROM crm_customers WHERE business_id = ? ORDER BY name', 'i', [$bid]);

$error = null;
$old = [
    'lead_id' => (int) ($_GET['lead_id'] ?? 0),
    'customer_id' => (int) ($_GET['customer_id'] ?? 0),
    'note' => '', 'channel' => 'call', 'due_at' => date('Y-m-d\TH:i', strtotime('+1 day 9:00')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $leadId = (int) ($_POST['lead_id'] ?? 0) ?: null;
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $old['lead_id'] = (int) $leadId;
    $old['customer_id'] = (int) $customerId;
    $old['note'] = tp_sanitize_text($_POST['note'] ?? '', 500);
    $old['channel'] = $_POST['channel'] ?? 'call';
    $old['due_at'] = tp_sanitize_text($_POST['due_at'] ?? '', 30);

    if ($old['note'] === '' || $old['due_at'] === '') {
        $error = 'A note and a due date/time are required.';
    } elseif (!$leadId && !$customerId) {
        $error = 'Please choose a lead or a customer for this follow-up.';
    } else {
        $dueSql = date('Y-m-d H:i:s', strtotime($old['due_at']));
        tp_execute(
            'INSERT INTO crm_followups (business_id, lead_id, customer_id, note, channel, due_at) VALUES (?,?,?,?,?,?)',
            'iiisss',
            [$bid, $leadId, $customerId, $old['note'], $old['channel'], $dueSql]
        );
        tp_flash_set('success', 'Follow-up added — e.g. "' . $old['note'] . '"');
        header('Location: ' . tp_url('crm/followups.php'));
        exit;
    }
}

$crmPageTitle = 'Add Follow-up';
$crmActive = 'followups';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card" style="max-width:560px;">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= tp_csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Related Lead</label>
      <select name="lead_id" class="form-select">
        <option value="0">— None —</option>
        <?php foreach ($leads as $l): ?><option value="<?= $l['id'] ?>" <?= $old['lead_id'] === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Related Customer</label>
      <select name="customer_id" class="form-select">
        <option value="0">— None —</option>
        <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= $old['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <small class="text-muted">Pick a lead, a customer, or both.</small>
    </div>
    <div class="mb-3">
      <label class="form-label">Reminder Note *</label>
      <input type="text" name="note" class="form-control" value="<?= e($old['note']) ?>" placeholder='e.g. "Call Ahmed tomorrow at 11 AM"' required>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Channel</label>
        <select name="channel" class="form-select">
          <?php foreach (['call' => 'Call', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting', 'email' => 'Email'] as $k => $l): ?>
            <option value="<?= $k ?>" <?= $old['channel'] === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Due *</label>
        <input type="datetime-local" name="due_at" class="form-control" value="<?= e($old['due_at']) ?>" required>
      </div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit">Add Follow-up</button>
      <a href="<?= tp_url('crm/followups.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
