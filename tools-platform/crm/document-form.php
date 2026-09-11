<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$customers = tp_query('SELECT id, name, phone, company FROM crm_customers WHERE business_id = ? ORDER BY name', 'i', [$bid]);
$products = tp_query('SELECT id, name, retail_price FROM crm_products WHERE business_id = ? ORDER BY name', 'i', [$bid]);

$error = null;
$docType = $_GET['doc_type'] ?? $_POST['doc_type'] ?? 'quotation';
if (!isset(CRM_DOC_TYPES[$docType])) {
    $docType = 'quotation';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $docType = $_POST['doc_type'] ?? 'quotation';
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $customerName = tp_sanitize_text($_POST['customer_name'] ?? '', 160);
    $customerPhone = tp_sanitize_text($_POST['customer_phone'] ?? '', 30);
    $customerCompany = tp_sanitize_text($_POST['customer_company'] ?? '', 160);
    $discountType = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $discountValue = (float) tp_sanitize_number($_POST['discount_value'] ?? 0);
    $taxPercent = (float) tp_sanitize_number($_POST['tax_percent'] ?? 0);
    $deliveryCharges = (float) tp_sanitize_number($_POST['delivery_charges'] ?? 0);
    $notes = tp_sanitize_text($_POST['notes'] ?? '', 1000);
    $items = json_decode($_POST['items_json'] ?? '[]', true) ?: [];

    if (!isset(CRM_DOC_TYPES[$docType])) {
        $error = 'Invalid document type.';
    } elseif ($customerName === '') {
        $error = 'Customer name is required.';
    } elseif (!$items) {
        $error = 'Add at least one line item.';
    } else {
        $subtotal = 0;
        $cleanItems = [];
        foreach ($items as $item) {
            $desc = tp_sanitize_text($item['description'] ?? '', 255);
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            if ($desc === '' || $qty <= 0) {
                continue;
            }
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $cleanItems[] = ['product_id' => (int) ($item['product_id'] ?? 0) ?: null, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $lineTotal];
        }

        if (!$cleanItems) {
            $error = 'Add at least one valid line item (description + quantity).';
        } else {
            $discountAmount = $discountType === 'percent' ? round($subtotal * $discountValue / 100, 2) : $discountValue;
            $afterDiscount = max($subtotal - $discountAmount, 0);
            $taxAmount = round($afterDiscount * $taxPercent / 100, 2);
            $total = round($afterDiscount + $taxAmount + $deliveryCharges, 2);
            $docNumber = crm_next_doc_number($bid, $docType);

            $result = tp_execute(
                'INSERT INTO crm_documents (business_id, customer_id, doc_type, doc_number, customer_name, customer_phone, customer_company, subtotal, discount_type, discount_value, tax_percent, delivery_charges, total, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                'iisssssdsdddds',
                [$bid, $customerId, $docType, $docNumber, $customerName, $customerPhone, $customerCompany, $subtotal, $discountType, $discountValue, $taxPercent, $deliveryCharges, $total, $notes]
            );
            $docId = $result['insert_id'];

            foreach ($cleanItems as $i => $item) {
                tp_execute(
                    'INSERT INTO crm_document_items (document_id, product_id, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?,?)',
                    'iisdddi',
                    [$docId, $item['product_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['line_total'], $i]
                );
            }

            if ($customerId && in_array($docType, ['invoice', 'sales_order', 'receipt'], true)) {
                tp_execute(
                    'INSERT INTO crm_ledger_entries (business_id, customer_id, document_id, entry_type, amount, entry_date, note) VALUES (?,?,?,?,?,CURDATE(),?)',
                    'iiisds',
                    [$bid, $customerId, $docId, 'sale', $total, crm_doc_type_label($docType) . ' ' . $docNumber]
                );
            }

            tp_flash_set('success', crm_doc_type_label($docType) . ' ' . $docNumber . ' created.');
            header('Location: ' . tp_url('crm/document-view.php?id=' . $docId));
            exit;
        }
    }
}

$selectedCustomerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

$crmPageTitle = 'New Document';
$crmActive = 'documents';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card mb-3">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" id="docForm">
    <?= tp_csrf_field() ?>
    <input type="hidden" name="items_json" id="itemsJson">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Document Type</label>
        <select name="doc_type" class="form-select">
          <?php foreach (CRM_DOC_TYPES as $key => $info): ?>
            <option value="<?= $key ?>" <?= $docType === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">Customer</label>
        <select id="customerSelect" class="form-select">
          <option value="0">— Walk-in / type manually below —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-phone="<?= e($c['phone']) ?>" data-company="<?= e($c['company']) ?>" <?= $selectedCustomerId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['company'] ? ' (' . e($c['company']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><input type="hidden" name="customer_id" id="customerIdField" value="<?= $selectedCustomerId ?>"><label class="form-label">Customer Name *</label><input type="text" name="customer_name" id="customerNameField" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="customer_phone" id="customerPhoneField" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">Company</label><input type="text" name="customer_company" id="customerCompanyField" class="form-control"></div>
    </div>

    <h3 class="h6 fw-bold mb-2">Items</h3>
    <div id="itemsContainer"></div>
    <button type="button" class="tp-btn tp-btn-outline tp-btn-sm mb-3" id="addItemBtn" style="color:var(--tp-indigo);border-color:var(--tp-indigo);"><i class="bi bi-plus-lg"></i> Add Item</button>

    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Discount</label>
        <div class="input-group">
          <input type="number" step="0.01" min="0" name="discount_value" id="discountValue" class="form-control" value="0">
          <select name="discount_type" id="discountType" class="form-select" style="max-width:90px;">
            <option value="percent">%</option>
            <option value="fixed">Rs</option>
          </select>
        </div>
      </div>
      <div class="col-md-3"><label class="form-label">Tax (%)</label><input type="number" step="0.01" min="0" name="tax_percent" id="taxPercent" class="form-control" value="0"></div>
      <div class="col-md-3"><label class="form-label">Delivery Charges</label><input type="number" step="0.01" min="0" name="delivery_charges" id="deliveryCharges" class="form-control" value="0"></div>
      <div class="col-md-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
    </div>

    <div class="crm-doc-sheet border-0 p-0 mt-3" style="max-width:320px;margin-left:auto;">
      <div class="doc-totals">
        <div><span>Subtotal</span><strong id="sumSubtotal">Rs 0</strong></div>
        <div><span>Discount</span><strong id="sumDiscount">Rs 0</strong></div>
        <div><span>Tax</span><strong id="sumTax">Rs 0</strong></div>
        <div><span>Delivery</span><strong id="sumDelivery">Rs 0</strong></div>
        <div class="grand-total"><span>Total</span><strong id="sumTotal">Rs 0</strong></div>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit">Create Document</button>
      <a href="<?= tp_url('crm/documents.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>

<script>
const CRM_PRODUCTS = <?= json_encode(array_map(fn($p) => ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (float) $p['retail_price']], $products)) ?>;
const CURRENCY = <?= json_encode(crm_currency_symbol()) ?>;
let itemRowCount = 0;

function crmMoney(n) { return CURRENCY + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function crmAddItemRow() {
  const idx = itemRowCount++;
  const row = document.createElement('div');
  row.className = 'row g-2 mb-2 crm-doc-items-row align-items-center';
  row.dataset.rowIndex = idx;
  const productOptions = ['<option value="0">— Custom item —</option>'].concat(
    CRM_PRODUCTS.map((p) => `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`)
  ).join('');
  row.innerHTML = `
    <div class="col-md-3"><select class="form-select item-product">${productOptions}</select></div>
    <div class="col-md-4"><input type="text" class="form-control item-desc" placeholder="Description" required></div>
    <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control item-qty" placeholder="Qty" value="1"></div>
    <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control item-price" placeholder="Unit price" value="0"></div>
    <div class="col-md-1"><button type="button" class="btn btn-sm text-danger item-remove"><i class="bi bi-x-lg"></i></button></div>
  `;
  document.getElementById('itemsContainer').appendChild(row);

  row.querySelector('.item-product').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (this.value !== '0') {
      row.querySelector('.item-desc').value = opt.textContent;
      row.querySelector('.item-price').value = opt.dataset.price;
    }
    crmRecalc();
  });
  row.querySelectorAll('.item-qty, .item-price').forEach((el) => el.addEventListener('input', crmRecalc));
  row.querySelector('.item-remove').addEventListener('click', () => { row.remove(); crmRecalc(); });
}

function crmRecalc() {
  let subtotal = 0;
  document.querySelectorAll('.crm-doc-items-row').forEach((row) => {
    const qty = Number(row.querySelector('.item-qty').value) || 0;
    const price = Number(row.querySelector('.item-price').value) || 0;
    subtotal += qty * price;
  });
  const discountType = document.getElementById('discountType').value;
  const discountValue = Number(document.getElementById('discountValue').value) || 0;
  const discountAmount = discountType === 'percent' ? subtotal * discountValue / 100 : discountValue;
  const afterDiscount = Math.max(subtotal - discountAmount, 0);
  const taxPercent = Number(document.getElementById('taxPercent').value) || 0;
  const taxAmount = afterDiscount * taxPercent / 100;
  const delivery = Number(document.getElementById('deliveryCharges').value) || 0;
  const total = afterDiscount + taxAmount + delivery;

  document.getElementById('sumSubtotal').textContent = crmMoney(subtotal);
  document.getElementById('sumDiscount').textContent = crmMoney(discountAmount);
  document.getElementById('sumTax').textContent = crmMoney(taxAmount);
  document.getElementById('sumDelivery').textContent = crmMoney(delivery);
  document.getElementById('sumTotal').textContent = crmMoney(total);
}

document.getElementById('addItemBtn').addEventListener('click', crmAddItemRow);
['discountValue', 'discountType', 'taxPercent', 'deliveryCharges'].forEach((id) => {
  document.getElementById(id).addEventListener('input', crmRecalc);
  document.getElementById(id).addEventListener('change', crmRecalc);
});

document.getElementById('customerSelect').addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  document.getElementById('customerIdField').value = this.value;
  if (this.value !== '0') {
    document.getElementById('customerNameField').value = opt.dataset.name || '';
    document.getElementById('customerPhoneField').value = opt.dataset.phone || '';
    document.getElementById('customerCompanyField').value = opt.dataset.company || '';
  }
});

document.getElementById('docForm').addEventListener('submit', function (e) {
  const items = [...document.querySelectorAll('.crm-doc-items-row')].map((row) => ({
    product_id: Number(row.querySelector('.item-product').value) || 0,
    description: row.querySelector('.item-desc').value.trim(),
    quantity: Number(row.querySelector('.item-qty').value) || 0,
    unit_price: Number(row.querySelector('.item-price').value) || 0,
  })).filter((i) => i.description && i.quantity > 0);

  if (!items.length) {
    e.preventDefault();
    alert('Add at least one item with a description and quantity.');
    return;
  }
  document.getElementById('itemsJson').value = JSON.stringify(items);
});

// Pre-select customer if one was passed in the URL, then seed one empty row.
<?php if ($selectedCustomerId): $sel = tp_query_one('SELECT name, phone, company FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$selectedCustomerId, $bid]); if ($sel): ?>
document.getElementById('customerNameField').value = <?= json_encode($sel['name']) ?>;
document.getElementById('customerPhoneField').value = <?= json_encode($sel['phone']) ?>;
document.getElementById('customerCompanyField').value = <?= json_encode($sel['company']) ?>;
<?php endif; endif; ?>
crmAddItemRow();
</script>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
