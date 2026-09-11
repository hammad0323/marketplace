<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();
$business = tp_current_business();

$id = (int) ($_GET['id'] ?? 0);
$doc = tp_query_one('SELECT * FROM crm_documents WHERE id = ? AND business_id = ?', 'ii', [$id, $bid]);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    tp_require_csrf();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['draft', 'sent', 'paid', 'cancelled'], true)) {
        tp_execute('UPDATE crm_documents SET status = ? WHERE id = ? AND business_id = ?', 'sii', [$newStatus, $id, $bid]);
        tp_flash_set('success', 'Status updated.');
    }
    header('Location: ' . tp_url('crm/document-view.php?id=' . $id));
    exit;
}

$items = tp_query('SELECT * FROM crm_document_items WHERE document_id = ? ORDER BY sort_order', 'i', [$id]);
$discountAmount = $doc['discount_type'] === 'percent' ? round($doc['subtotal'] * $doc['discount_value'] / 100, 2) : (float) $doc['discount_value'];
$afterDiscount = max($doc['subtotal'] - $discountAmount, 0);
$taxAmount = round($afterDiscount * $doc['tax_percent'] / 100, 2);

$crmPageTitle = crm_doc_type_label($doc['doc_type']) . ' ' . $doc['doc_number'];
$crmActive = 'documents';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
  <form method="post" class="d-flex gap-2 align-items-center">
    <?= tp_csrf_field() ?>
    <input type="hidden" name="action" value="set_status">
    <label class="small text-muted m-0">Status:</label>
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
      <?php foreach (['draft', 'sent', 'paid', 'cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $doc['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="d-flex gap-2">
    <?php if ($doc['customer_phone']): ?>
      <a href="<?= crm_whatsapp_link($doc['customer_phone'], crm_doc_type_label($doc['doc_type']) . ' ' . $doc['doc_number'] . ' — Total: ' . crm_currency_symbol() . number_format((float) $doc['total'], 2)) ?>" target="_blank" class="tp-btn tp-btn-sm" style="background:#25D366;color:#fff;"><i class="bi bi-whatsapp"></i> Send via WhatsApp</a>
    <?php endif; ?>
    <button onclick="window.print()" class="tp-btn tp-btn-sm tp-btn-light"><i class="bi bi-printer"></i> Print / Save PDF</button>
  </div>
</div>

<div class="crm-doc-sheet">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <div class="doc-title"><?= e($business['business_name']) ?></div>
      <?php if ($business['phone']): ?><div class="small text-muted"><?= e($business['phone']) ?></div><?php endif; ?>
      <div class="small text-muted"><?= e($business['email']) ?></div>
    </div>
    <div class="text-end">
      <div class="h5 fw-bold m-0"><?= crm_doc_type_label($doc['doc_type']) ?></div>
      <div class="text-muted"><?= e($doc['doc_number']) ?></div>
      <div class="text-muted small"><?= date('F j, Y', strtotime($doc['created_at'])) ?></div>
    </div>
  </div>

  <hr>
  <div class="row">
    <div class="col-6">
      <div class="small text-muted text-uppercase">Bill To</div>
      <div class="fw-bold"><?= e($doc['customer_name']) ?></div>
      <?php if ($doc['customer_company']): ?><div><?= e($doc['customer_company']) ?></div><?php endif; ?>
      <?php if ($doc['customer_phone']): ?><div><?= e($doc['customer_phone']) ?></div><?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= e($item['description']) ?></td>
        <td class="text-end"><?= rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?></td>
        <td class="text-end"><?= crm_currency_symbol() . number_format((float) $item['unit_price'], 2) ?></td>
        <td class="text-end"><?= crm_currency_symbol() . number_format((float) $item['line_total'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="doc-totals">
    <div><span>Subtotal</span><strong><?= crm_currency_symbol() . number_format((float) $doc['subtotal'], 2) ?></strong></div>
    <?php if ($discountAmount > 0): ?><div><span>Discount</span><strong>-<?= crm_currency_symbol() . number_format($discountAmount, 2) ?></strong></div><?php endif; ?>
    <?php if ($taxAmount > 0): ?><div><span>Tax (<?= rtrim(rtrim(number_format((float) $doc['tax_percent'], 2), '0'), '.') ?>%)</span><strong><?= crm_currency_symbol() . number_format($taxAmount, 2) ?></strong></div><?php endif; ?>
    <?php if ($doc['delivery_charges'] > 0): ?><div><span>Delivery</span><strong><?= crm_currency_symbol() . number_format((float) $doc['delivery_charges'], 2) ?></strong></div><?php endif; ?>
    <div class="grand-total"><span>Total</span><strong><?= crm_currency_symbol() . number_format((float) $doc['total'], 2) ?></strong></div>
  </div>

  <?php if ($doc['notes']): ?>
    <div class="mt-4 small text-muted"><strong>Notes:</strong> <?= nl2br(e($doc['notes'])) ?></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
