<?php
require __DIR__ . '/includes/init.php';
admin_require('pos');
$session = db_row("SELECT * FROM pos_sessions WHERE admin_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [admin_id()]);
$zones = delivery_zones();
$methods = db_all('SELECT code, name, requires_reference FROM payment_methods WHERE is_active = 1 AND show_in_pos = 1 ORDER BY (code = "cash") DESC, (applies_to = "pos") DESC, sort_order');
admin_header('POS / Counter', 'pos');
if (!$session): ?>
<div class="card mx-auto" style="max-width:440px"><div class="card-body p-4 text-center">
  <i class="bi bi-cash-stack display-5 text-brand"></i>
  <h2 class="h4 mt-2">Open the register</h2>
  <p class="text-muted small">Count the cash in the drawer to start your shift. Pending orders and drafts can be saved without opening the register.</p>
  <form id="openForm" class="text-start"><?= af_text('opening_cash', 'Opening cash in drawer (' . setting('currency_symbol', 'Rs.') . ')', '0', ['type' => 'number', 'min' => 0, 'step' => '1', 'required' => true]) ?>
    <button class="btn btn-brand w-100 btn-lg">Open register</button></form>
  <a href="#" class="small d-block mt-3" onclick="document.getElementById('posSkip').classList.remove('d-none');this.remove();return false;">Continue without opening (pending orders / drafts only)</a>
</div></div>
<script>document.addEventListener('DOMContentLoaded', function () { $('#openForm').on('submit', function (e) { e.preventDefault(); FSA.post('ajax/pos.php', { action: 'open_session', opening_cash: this.opening_cash.value }).done(function () { location.reload(); }); }); });</script>
<div id="posSkip" class="d-none mt-3">
<?php else: ?>
<div>
<?php endif; ?>
<div class="pos-wrap" id="pos" data-session="<?= $session ? (int) $session['id'] : 0 ?>">
  <section class="pos-left" aria-label="Products">
    <div class="pos-search">
      <div class="input-group input-group-lg">
        <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
        <input type="search" id="posSearch" class="form-control" placeholder="Scan barcode or search name / SKU / ISBN  (F2)" autocomplete="off" autofocus aria-label="Search products">
      </div>
      <div class="d-flex gap-2 mt-2 small text-muted"><span><kbd>F2</kbd> search</span><span><kbd>F8</kbd> pending</span><span><kbd>F9</kbd> complete sale</span><span class="ms-auto"><?php if ($session): ?>Register open since <?= fmt_datetime($session['opened_at']) ?> · <a href="#" id="closeReg">Close register</a><?php else: ?><span class="text-warning">Register closed</span><?php endif; ?></span></div>
    </div>
    <div class="pos-results" id="posResults"><div class="text-muted p-3">Loading products…</div></div>
  </section>

  <section class="pos-right" aria-label="Current sale">
    <div class="p-2 border-bottom">
      <div class="d-flex gap-2 align-items-center">
        <div class="flex-grow-1 position-relative">
          <input type="search" id="custSearch" class="form-control form-control-sm" placeholder="Customer: search name or phone…" autocomplete="off" aria-label="Customer search">
          <div class="list-group position-absolute w-100 shadow" id="custResults" style="z-index:20"></div>
        </div>
        <button class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#custModal" title="New customer"><i class="bi bi-person-plus"></i></button>
        <button class="btn btn-sm btn-outline-secondary" id="draftsBtn" title="Saved drafts"><i class="bi bi-folder2-open"></i> Drafts</button>
      </div>
      <div class="small mt-1" id="custBox"><span class="text-muted">Walk-in customer</span></div>
    </div>
    <div class="pos-cart" id="posCart"><div class="text-center text-muted p-4 small" id="emptyCart"><i class="bi bi-cart3 fs-2 d-block"></i>Scan or click products to add</div></div>
    <div class="pos-totals">
      <div class="row-t"><span>Items</span><strong id="tItems">0</strong></div>
      <div class="row-t"><span>Subtotal</span><strong id="tSub">Rs. 0</strong></div>
      <div class="row-t align-items-center gap-2"><span>Discount</span>
        <div class="input-group input-group-sm" style="max-width:190px"><input type="number" min="0" step="1" id="discVal" class="form-control" value="0" aria-label="Discount value">
          <select id="discType" class="form-select" style="max-width:70px" aria-label="Discount type"><option value="amount">Rs</option><option value="percent">%</option></select></div>
        <strong id="tDisc">- Rs. 0</strong></div>
      <div class="d-flex gap-1 my-2" role="group" aria-label="Fulfillment">
        <input type="radio" class="btn-check" name="ful" id="fulW" value="walkin" checked><label class="btn btn-sm btn-outline-secondary flex-fill" for="fulW"><i class="bi bi-person-walking"></i> Walk-in</label>
        <input type="radio" class="btn-check" name="ful" id="fulP" value="pickup"><label class="btn btn-sm btn-outline-secondary flex-fill" for="fulP"><i class="bi bi-shop"></i> Pickup</label>
        <input type="radio" class="btn-check" name="ful" id="fulD" value="delivery"><label class="btn btn-sm btn-outline-secondary flex-fill" for="fulD"><i class="bi bi-truck"></i> Delivery</label>
      </div>
      <div id="pickupBox" class="d-none mb-2"><label class="small" for="pickupAt">Pickup date &amp; time</label><input type="datetime-local" id="pickupAt" class="form-control form-control-sm"></div>
      <div id="deliveryBox" class="d-none mb-2">
        <select id="zoneSel" class="form-select form-select-sm mb-1" aria-label="Delivery area"><option value="">Delivery area…</option><?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>" data-fee="<?= e($z['delivery_fee']) ?>" data-free="<?= e($z['free_delivery_over']) ?>"><?= e($z['area'] . ', ' . $z['city']) ?> (<?= money($z['delivery_fee']) ?>)</option><?php endforeach; ?></select>
        <input id="addrLine" class="form-control form-control-sm mb-1" placeholder="Complete address" aria-label="Address">
        <div class="input-group input-group-sm"><span class="input-group-text">Delivery fee</span><input type="number" min="0" id="delFee" class="form-control" value="0" aria-label="Delivery fee"></div>
      </div>
      <div class="d-flex gap-2 mb-1">
        <select id="payMethod" class="form-select form-select-sm" aria-label="Payment method"><?php foreach ($methods as $m): ?><option value="<?= e($m['code']) ?>" data-ref="<?= (int) $m['requires_reference'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
        <div class="form-check form-switch small text-nowrap align-self-center"><input class="form-check-input" type="checkbox" id="isRashan"><label class="form-check-label" for="isRashan">Rashan</label></div>
      </div>
      <input id="payRef" class="form-control form-control-sm mb-1 d-none" placeholder="Transaction reference" aria-label="Payment reference">
      <div class="d-flex gap-2 align-items-center mb-1" id="cashBox"><label class="small text-nowrap" for="tendered">Cash received</label><input type="number" min="0" id="tendered" class="form-control form-control-sm"><span class="small text-nowrap">Change: <strong id="tChange">Rs. 0</strong></span></div>
      <input id="posNotes" class="form-control form-control-sm mb-2" placeholder="Notes (optional)" aria-label="Notes">
      <div class="d-flex justify-content-between align-items-end"><span>Total</span><span class="pos-grand" id="tGrand">Rs. 0</span></div>
    </div>
    <div class="pos-actions">
      <button class="btn btn-brand btn-lg" id="btnComplete" style="grid-column: span 2" <?= $session ? '' : 'disabled title="Open the register first"' ?>><i class="bi bi-check2-circle me-1"></i>Complete sale (F9)</button>
      <button class="btn btn-warning" id="btnPending"><i class="bi bi-hourglass-split me-1"></i>Save pending (F8)</button>
      <button class="btn btn-outline-secondary" id="btnDraft"><i class="bi bi-save me-1"></i>Save draft</button>
      <button class="btn btn-outline-danger btn-sm" id="btnClear" style="grid-column: span 2"><i class="bi bi-x-circle me-1"></i>Clear sale</button>
    </div>
  </section>
</div>
</div>

<!-- New customer -->
<div class="modal fade" id="custModal" tabindex="-1" aria-labelledby="cmTitle"><div class="modal-dialog"><form class="modal-content" id="custForm">
  <div class="modal-header"><h2 class="modal-title h5" id="cmTitle">New customer</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <?= af_text('name', 'Name *', '', ['required' => true]) ?><?= af_text('phone', 'Phone *', '', ['required' => true, 'inputmode' => 'tel']) ?><?= af_text('email', 'Email', '', ['type' => 'email']) ?>
    <?php $zo = ['' => '— No address —']; foreach ($zones as $z) $zo[$z['id']] = $z['area'] . ', ' . $z['city']; ?>
    <?= af_select('zone_id', 'Area (for delivery)', $zo) ?><?= af_text('address_line', 'Address', '') ?>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Save &amp; select</button></div>
</form></div></div>

<!-- Drafts -->
<div class="modal fade" id="draftsModal" tabindex="-1" aria-labelledby="dmTitle"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="dmTitle">Saved drafts</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body p-0"><div class="list-group list-group-flush" id="draftList"></div></div>
</div></div></div>

<!-- Done -->
<div class="modal fade" id="doneModal" tabindex="-1" aria-labelledby="doneTitle"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-3">
  <div class="modal-body"><i class="bi bi-check-circle-fill text-success display-4"></i><h2 class="h4 mt-2" id="doneTitle"></h2><div class="fs-3 fw-bold js-done-change"></div>
    <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
      <a class="btn btn-brand js-print-receipt" target="_blank"><i class="bi bi-receipt me-1"></i>Print receipt</a>
      <a class="btn btn-outline-secondary js-print-pick" target="_blank"><i class="bi bi-list-check me-1"></i>Picking list</a>
      <a class="btn btn-outline-secondary js-view-order"><i class="bi bi-eye me-1"></i>View order</a>
      <button class="btn btn-dark" data-bs-dismiss="modal">New sale (Esc)</button>
    </div></div>
</div></div></div>

<!-- Close register -->
<div class="modal fade" id="closeModal" tabindex="-1" aria-labelledby="crTitle"><div class="modal-dialog"><form class="modal-content" id="closeForm">
  <div class="modal-header"><h2 class="modal-title h5" id="crTitle">Close register</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><div class="js-sess-summary small mb-3"></div><?= af_text('closing_cash', 'Counted cash in drawer', '', ['type' => 'number', 'min' => 0, 'required' => true]) ?><?= af_text('notes', 'Notes', '') ?></div>
  <div class="modal-footer"><button class="btn btn-danger">Close register</button></div>
</form></div></div>
<?php admin_footer([url('admin/assets/pos.js') . '?v=' . @filemtime(__DIR__ . '/assets/pos.js')]); ?>
<script>window.POS_LOAD = <?= input_int('load') ?>;</script>
