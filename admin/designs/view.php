<?php
/** Design request: check the design, confirm the print price per piece and message the customer. */
require __DIR__ . '/../includes/init.php';
admin_require('design_studio');
require __DIR__ . '/sheet.inc.php';
$id = input_int('id');
$r = db_row('SELECT * FROM design_requests WHERE id = ?', [$id]);
if (!$r) {
    flash('error', 'Request not found.');
    redirect('admin/designs/');
}
$design = db_row('SELECT * FROM designs WHERE id = ?', [(int) $r['design_id']]);
$design['spec_data'] = json_decode((string) $design['spec'], true) ?: [];
if (is_post()) {
    $status = input('status', $r['status'], 'post');
    $status = isset(design_request_statuses()[$status]) ? $status : $r['status'];
    $quote = input('quoted_print_price', '', 'post') === '' ? null : max(0, (float) input_float('quoted_print_price', 0));
    if ($quote !== null && $status === 'new') {
        $status = 'quoted';
    }
    if ($status === 'quoted' && $quote === null) {
        flash('error', 'Enter the print price per piece before confirming the quote.');
        redirect('admin/designs/view.php?id=' . $id);
    }
    $message = mb_substr(input('admin_message', '', 'post'), 0, 2000);
    $wasQuoted = $r['status'] === 'quoted' && (string) $r['quoted_print_price'] === (string) $quote;
    db_update('design_requests', ['status' => $status, 'quoted_print_price' => $quote, 'admin_message' => nullable($message),
        'admin_notes' => nullable(mb_substr(input('admin_notes', '', 'post'), 0, 5000)), 'quoted_at' => $status === 'quoted' && !$wasQuoted ? date('Y-m-d H:i:s') : $r['quoted_at']], 'id = ?', [$id]);
    if (input_bool('notify') && $status === 'quoted' && !$wasQuoted) {
        $nr = db_row('SELECT * FROM design_requests WHERE id = ?', [$id]);
        $t = design_request_totals($nr, $design);
        mail_design_quote($nr, 'Your design price is confirmed', 'Good news — the total for your design is ' . money($t['total']) . ' (' . (int) $nr['qty'] . ' piece' . ((int) $nr['qty'] === 1 ? '' : 's') . ').' . ($message ? "\n\n" . $message : '') . "\n\nTap the button below to order.");
    }
    audit_log('design_request_updated', 'design_request', $id, ['status' => $status, 'print' => $quote]);
    flash('success', $status === 'quoted' && !$wasQuoted ? 'Price confirmed — the customer can now order from their link.' : 'Request updated.');
    redirect('admin/designs/view.php?id=' . $id);
}
$totals = design_request_totals($r, $design);
$auto = design_price($design['spec_data']['elements'] ?? []);
$wa = preg_replace('/\D/', '', $r['phone']);
if (str_starts_with($wa, '0')) $wa = '92' . substr($wa, 1);
admin_header('Design request ' . $r['reference']);
?>
<div class="d-flex gap-2 align-items-center mb-3 flex-wrap"><?= design_request_badge($r['status']) ?><span class="small text-muted">Received <?= e(format_datetime($r['created_at'])) ?></span>
    <?php if ($r['order_id']): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/orders/view.php?id=' . $r['order_id'])) ?>"><i class="bi bi-receipt"></i> View order</a><?php endif; ?>
    <a class="btn btn-sm btn-success ms-auto" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Assalam o Alaikum ' . $r['name'] . ', about your design ' . $r['reference'] . ' at ' . store_name() . ': ' . design_request_link($r)) ?>"><i class="bi bi-whatsapp"></i> WhatsApp customer</a>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(design_request_link($r)) ?>" target="_blank"><i class="bi bi-eye"></i> Customer view</a></div>
<div class="card a-card"><div class="card-header">Design</div><div class="card-body"><?php admin_design_sheet($design); ?></div></div>
<div class="row g-4"><div class="col-xl-7">
    <div class="card a-card"><div class="card-header">Order details</div><div class="card-body">
        <table class="table table-sm small mb-3"><thead><tr><th>Size</th><th>Qty</th><th>Garment</th><th>Print</th><th class="text-end">Total</th></tr></thead><tbody>
            <?php foreach ($totals['lines'] as $l): ?><tr><td><?= e($l['size']) ?></td><td><?= (int) $l['qty'] ?></td><td><?= e(money($l['garment'])) ?></td><td><?= e(money($l['print'])) ?></td><td class="text-end"><?= e(money($l['total'])) ?></td></tr><?php endforeach; ?>
            <tr class="fw-bold"><td colspan="4"><?= $totals['final'] ? 'Confirmed total' : 'Total at estimated print price' ?></td><td class="text-end"><?= e(money($totals['total'])) ?></td></tr></tbody></table>
        <p class="small mb-1"><strong>Automatic print estimate:</strong> <?= e(money($r['estimate_print'])) ?> per piece when sent<?= abs($auto['total'] - (float) $r['estimate_print']) > 0.01 ? ' · ' . e(money($auto['total'])) . ' with today’s prices' : '' ?>
            <span class="text-muted">(<?= (int) $auto['images'] ?> image<?= $auto['images'] === 1 ? '' : 's' ?>, <?= (int) $auto['texts'] ?> text<?= $auto['texts'] === 1 ? '' : 's' ?><?= $auto['discount'] ? ', multi-image discount ' . e(money($auto['discount'])) : '' ?>)</span></p>
        <?php if ($r['notes']): ?><div class="a-help mt-2"><strong>Customer notes:</strong> <?= nl2br(e($r['notes'])) ?></div><?php endif; ?>
    </div></div>
</div><div class="col-xl-5">
    <div class="card a-card"><div class="card-header">Customer</div><div class="card-body small">
        <strong><?= e($r['name']) ?></strong><br><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?= $r['email'] ? '<br><a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?><br><?= e($r['city']) ?>
    </div></div>
    <div class="card a-card"><div class="card-header">Confirm price</div><div class="card-body">
        <form method="post"><?= csrf_field() ?>
            <?= a_input('quoted_print_price', 'Print price per piece (added to the garment price)', $r['quoted_print_price'] ?? $r['estimate_print'], 'number', ['min' => 0, 'step' => '1'], 'Saving a price confirms the quote: the customer sees the total and an “Order now” button on their link.') ?>
            <?= a_select('status', 'Status', design_request_statuses(), $r['status']) ?>
            <?= a_textarea('admin_message', 'Message to customer (optional)', $r['admin_message'], 3) ?>
            <div class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="notify" value="1" id="n"<?= mail_enabled() && $r['email'] ? ' checked' : ' disabled' ?>><label class="form-check-label" for="n">Email the customer when the price is confirmed<?= $r['email'] ? '' : ' (no email given — send the link on WhatsApp)' ?></label></div>
            <?= a_textarea('admin_notes', 'Internal notes', $r['admin_notes'], 2) ?>
            <button class="btn btn-dark w-100">Save</button>
        </form>
    </div></div>
</div></div>
<?php admin_footer();
