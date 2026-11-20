<?php
require __DIR__ . '/_init.php';
$id = (int) ($_GET['id'] ?? 0);
$order = db_row('SELECT * FROM orders WHERE id = ? AND shop_id = ?', [$id, owner_shop_id()]);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('owner/orders.php');
}
if (is_post()) {
    $err = order_owner_action($order, (string) ($_POST['action'] ?? ''), $_POST, $OWNER);
    flash($err ? 'error' : 'success', $err ?: 'Order updated.');
    redirect('owner/order.php?id=' . $id);
}
$d = order_detail($order, true);
$wa = preg_replace('/\D/', '', $order['customer_phone']);
if (str_starts_with($wa, '0')) {
    $wa = '92' . substr($wa, 1);
}
$lbl = fn($v) => ['bw' => 'Black & White', 'color' => 'Full colour', 'single' => 'Single-sided', 'double' => 'Double-sided'][$v] ?? $v;
owner_header('Order ' . $order['order_number']);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a class="btn btn-light btn-sm" href="<?= e(path('owner/orders.php')) ?>"><i class="bi bi-arrow-left"></i></a>
    <?= order_badge($order['status']) ?>
    <span class="p-chip"><?= $order['pricing_mode'] === 'auto' ? 'Automatic price' : 'Quote' ?></span>
    <span class="p-chip"><i class="bi bi-wallet2"></i> <?= ['pay_at_shop' => 'Pay at shop', 'advance_required' => 'Advance required', 'advance_optional' => 'Advance optional'][$order['payment_mode']] ?> · <?= e(str_replace('_', ' ', $order['payment_status'])) ?></span>
    <span class="text-muted small ms-1">Received <?= e(format_datetime($order['created_at'])) ?> via <?= $order['source'] === 'app' ? 'app' : 'website' ?></span>
    <span class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Assalam o Alaikum ' . $order['customer_name'] . ', about your print order ' . $order['order_number'] . ' at ' . $OWNER['shop']['name'] . ': ') ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <a class="btn btn-sm btn-outline-primary" href="tel:<?= e($order['customer_phone']) ?>"><i class="bi bi-telephone"></i> Call</a></span>
</div>
<div class="row g-3">
<div class="col-xl-8">
<?php foreach ($d['items'] as $n => $it): ?>
<div class="p-card"><div class="p-card-head"><span class="badge text-bg-light"><?= $n + 1 ?></span> <?= e($it['title']) ?><span class="ms-auto small text-muted"><?= e(trim($it['category'] . ($it['subcategory'] ? ' · ' . $it['subcategory'] : ''))) ?></span></div>
<div class="p-card-body"><div class="row g-3">
    <div class="col-md-6"><dl class="p-kv" style="grid-template-columns:120px 1fr">
        <dt>Paper</dt><dd><?= e($it['custom_size'] ?? $it['paper_size'] ?? '—') ?> · <?= e(ucfirst($it['orientation'])) ?></dd>
        <?php if ($it['color']): ?><dt>Print</dt><dd><?= e($lbl($it['color'])) ?><?= $it['sides'] ? ' · ' . e($lbl($it['sides'])) : '' ?></dd><?php endif; ?>
        <?php if ($it['paper_type'] || $it['gsm']): ?><dt>Paper type</dt><dd><?= e(trim(($it['paper_type'] ?? '') . ' ' . ($it['gsm'] ?? ''))) ?></dd><?php endif; ?>
        <dt>Quantity</dt><dd><strong><?= $it['quantity'] > 1 ? (int) $it['quantity'] . ' pieces' : (int) $it['pages'] . ' page' . ($it['pages'] > 1 ? 's' : '') . ' × ' . (int) $it['copies'] . ' cop' . ($it['copies'] > 1 ? 'ies' : 'y') ?></strong></dd>
        <?php if ($it['finishing']): ?><dt>Finishing</dt><dd><?= e(implode(', ', $it['finishing'])) ?></dd><?php endif; ?>
        <?php if ($it['page_selection']): ?><dt>Pages</dt><dd class="small"><?= e(implode(', ', array_map(fn($p) => $p['page'] . ($p['rotate'] ? ' (↻' . $p['rotate'] . '°)' : ''), $it['page_selection']))) ?></dd><?php endif; ?>
        <?php $l = $it['layout'] ?: []; if ($l): ?><dt>Layout</dt><dd class="small"><?= e(['fit' => 'Fit to page', 'fill' => 'Fill page', 'actual' => 'Actual size', 'grid' => 'Photo sheet', 'id_pair' => 'CNIC front + back'][$l['fit'] ?? 'fit'] ?? '') ?>
            <?= isset($l['margin_mm']) ? ' · margins ' . (float) $l['margin_mm'] . ' mm' : '' ?><?= !empty($l['scale']) && (float) $l['scale'] !== 100.0 ? ' · scale ' . (float) $l['scale'] . '%' : '' ?><?= !empty($l['crop']) ? ' · cropped' : '' ?><?= !empty($l['rotate']) ? ' · rotated ' . (int) $l['rotate'] . '°' : '' ?></dd><?php endif; ?>
        <?php if ($it['notes']): ?><dt>Notes</dt><dd class="small"><?= nl2br(e($it['notes'])) ?></dd><?php endif; ?>
    </dl></div>
    <div class="col-md-6">
        <p class="fw-semibold small mb-2">Files</p>
        <?php foreach ($it['files'] as $f): ?>
            <div class="d-flex align-items-center gap-2 border rounded-3 p-2 mb-2<?= $f['kind'] === 'print_ready' ? ' border-success bg-success-subtle' : '' ?>">
                <i class="bi bi-<?= $f['kind'] === 'print_ready' ? 'printer' : (str_starts_with($f['mime'], 'image/') ? 'image' : (str_contains($f['mime'], 'pdf') ? 'file-earmark-pdf' : 'file-earmark-word')) ?> fs-4"></i>
                <div class="flex-grow-1 small"><strong><?= ['original' => 'Original', 'print_ready' => 'Print-ready PDF', 'converted' => 'Converted PDF', 'payment_proof' => 'Payment proof'][$f['kind']] ?></strong>
                    <div class="text-muted text-truncate" style="max-width:220px"><?= e($f['name']) ?><?= $f['pages'] ? ' · ' . (int) $f['pages'] . ' p' : '' ?></div><?php if ($f['note']): ?><div class="text-warning"><?= e($f['note']) ?></div><?php endif; ?></div>
                <?php if ($f['deleted']): ?><span class="small text-muted">Deleted</span><?php else: ?>
                <a class="btn btn-sm btn-light" target="_blank" href="<?= e($f['url']) ?>" title="Open"><i class="bi bi-eye"></i></a><a class="btn btn-sm btn-light" href="<?= e($f['download_url']) ?>" title="Download"><i class="bi bi-download"></i></a><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$it['files']): ?><p class="small text-muted">No files<?= $it['item_type'] === 'custom' ? ' — see notes.' : '.' ?></p><?php endif; ?>
        <?php if ($it['price_breakdown']): ?><table class="table table-sm small mt-2 mb-0"><?php foreach ($it['price_breakdown'] as $b): ?><tr><td class="text-muted"><?= e($b['label']) ?></td><td class="text-end"><?= e(money($b['amount'])) ?></td></tr><?php endforeach; ?>
            <tr><td><strong>Estimate</strong></td><td class="text-end"><strong><?= $it['estimated_price'] !== null ? e(money($it['estimated_price'])) : '—' ?></strong></td></tr></table><?php endif; ?>
    </div>
</div></div></div>
<?php endforeach; ?>
<?php if ($order['special_instructions']): ?><div class="p-card"><div class="p-card-head"><i class="bi bi-chat-left-text"></i> Customer instructions</div><div class="p-card-body"><?= nl2br(e($order['special_instructions'])) ?></div></div><?php endif; ?>
<?php if ($d['files_deleted']): ?><p class="small text-muted"><i class="bi bi-shield-check"></i> Files were deleted automatically after the retention period.</p><?php endif; ?>
</div>
<div class="col-xl-4">
<div class="p-card"><div class="p-card-head">Customer</div><div class="p-card-body"><strong><?= e($order['customer_name']) ?></strong><br><a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a></div></div>
<div class="p-card"><div class="p-card-head">Amount</div><div class="p-card-body">
    <dl class="p-kv" style="grid-template-columns:120px 1fr"><dt>Estimate</dt><dd><?= $d['estimated_amount'] !== null ? e(money($d['estimated_amount'])) : '<span class="text-muted">No matching prices</span>' ?></dd>
    <?php if ($d['quoted_amount'] !== null): ?><dt>Quoted</dt><dd><?= e(money($d['quoted_amount'])) ?></dd><?php endif; ?>
    <?php if ($d['final_amount'] !== null): ?><dt>Final</dt><dd><strong><?= e(money($d['final_amount'])) ?></strong></dd><?php endif; ?></dl>
</div></div>
<?php if ($d['owner_actions']): ?>
<div class="p-card"><div class="p-card-head">Next step</div><div class="p-card-body d-grid gap-2">
<?php foreach ($d['owner_actions'] as [$act, $label]): ?>
    <?php if (in_array($act, ['quote', 'reject', 'accept', 'confirm_payment', 'complete'], true)): ?>
        <button class="btn <?= $act === 'reject' ? 'btn-outline-danger' : ($act === 'accept' || $act === 'complete' ? 'btn-primary' : 'btn-outline-primary') ?>" data-bs-toggle="collapse" data-bs-target="#f-<?= $act ?>"><?= e($label) ?></button>
        <form method="post" id="f-<?= $act ?>" class="collapse border rounded-3 p-3"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>">
            <?php if ($act === 'quote'): ?><?= f_input('amount', 'Price for the whole order (PKR)', $d['quoted_amount'] ?? $d['estimated_amount'] ?? '', 'number', ['min' => 1, 'step' => 1, 'required' => true]) ?><?= f_input('note', 'Message to customer (optional)', '', 'text') ?>
            <?php elseif ($act === 'accept'): ?><?= f_input('amount', 'Final price (PKR) — change only to override', $d['quoted_amount'] ?? $d['estimated_amount'] ?? '', 'number', ['min' => 0, 'step' => 1]) ?>
            <?php elseif ($act === 'reject'): ?><?= f_input('reason', 'Reason shown to the customer', '', 'text', ['required' => true, 'placeholder' => 'e.g. File is unreadable, please re-upload']) ?>
            <?php else: ?><?= f_input('amount', 'Amount received (PKR)', $d['amount'] ?? '', 'number', ['min' => 0, 'step' => 1]) ?><?= f_select('method', 'Method', payment_methods(), 'cash') ?><?php endif; ?>
            <button class="btn btn-sm <?= $act === 'reject' ? 'btn-danger' : 'btn-primary' ?> w-100"><?= $act === 'reject' ? 'Reject order' : 'Confirm' ?></button></form>
    <?php else: ?>
        <form method="post" class="d-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><button class="btn btn-primary"><i class="bi bi-<?= $act === 'printing' ? 'printer' : 'bag-check' ?>"></i> <?= e($label) ?></button></form>
    <?php endif; ?>
<?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php if ($d['payments']): ?><div class="p-card"><div class="p-card-head">Payments</div><div class="list-group list-group-flush">
<?php foreach ($d['payments'] as $p): ?><div class="list-group-item small"><strong><?= e(money($p['amount'])) ?></strong> · <?= e(payment_methods()[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?> <span class="badge text-bg-<?= $p['status'] === 'confirmed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e($p['status']) ?></span>
    <?php if ($p['proof_url']): ?><a class="ms-1" target="_blank" href="<?= e($p['proof_url']) ?>">proof</a><?php endif; ?><div class="text-muted"><?= e(format_datetime($p['created_at'])) ?> · by <?= e($p['submitted_by']) ?></div></div><?php endforeach; ?></div></div><?php endif; ?>
<div class="p-card"><div class="p-card-head">Timeline</div><div class="p-card-body"><ul class="p-timeline">
<?php foreach (array_reverse($d['history']) as $h): ?><li><strong><?= e(order_statuses()[$h['status']] ?? $h['status']) ?></strong><?= $h['note'] ? ' — ' . e($h['note']) : '' ?><small><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['actor_type']) ?></small></li><?php endforeach; ?>
</ul></div></div>
</div></div>
<?php owner_footer();
