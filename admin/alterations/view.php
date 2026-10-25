<?php
require __DIR__ . '/../includes/init.php';
admin_require('alterations');
$id = input_int('id');
$r = db_row('SELECT * FROM alteration_requests WHERE id = ?', [$id]);
if (!$r) {
    flash('error', 'Request not found.');
    redirect('admin/alterations/');
}
if (is_post()) {
    $a = input('action', '', 'post');
    $note = mb_substr(input('note', '', 'post'), 0, 2000);
    if ($a === 'update') {
        $status = input('status', $r['status'], 'post');
        $status = isset(alteration_statuses()[$status]) ? $status : $r['status'];
        $quote = input('quoted_price', '', 'post') === '' ? null : max(0, (float) input_float('quoted_price', 0));
        $ready = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('ready_date', '', 'post')) ? input('ready_date', '', 'post') : null;
        if ($quote !== null && $status === 'new') {
            $status = 'quoted';
        }
        db_update('alteration_requests', ['status' => $status, 'quoted_price' => $quote, 'ready_date' => $ready, 'payment_status' => input('payment_status', 'unpaid', 'post') === 'paid' ? 'paid' : 'unpaid',
            'admin_notes' => nullable(mb_substr(input('admin_notes', '', 'post'), 0, 5000))], 'id = ?', [$id]);
        $changes = [];
        if ($status !== $r['status']) $changes[] = alteration_statuses()[$status];
        if ($quote !== null && (string) $quote !== (string) $r['quoted_price']) $changes[] = 'Price confirmed: ' . money($quote);
        if ($ready && $ready !== $r['ready_date']) $changes[] = 'Ready by ' . format_date($ready, 'D j M');
        if ($changes || $note !== '') {
            alteration_history_add($id, $status !== $r['status'] ? $status : null, trim(implode(' · ', $changes) . ($note !== '' ? ' — ' . $note : ''), ' —'), input_bool('visible'), admin_id());
        }
        if (input_bool('notify')) {
            $nr = db_row('SELECT * FROM alteration_requests WHERE id = ?', [$id]);
            mail_alteration_update($nr, 'Update on your alteration', implode("\n", $changes) . ($note ? "\n" . $note : ''));
        }
        audit_log('alteration_updated', 'alteration', $id, ['status' => $status, 'quote' => $quote]);
        flash('success', 'Request updated.');
    }
    redirect('admin/alterations/view.php?id=' . $id);
}
$items = db_all('SELECT * FROM alteration_request_items WHERE request_id = ?', [$id]);
$photos = db_all('SELECT * FROM alteration_request_photos WHERE request_id = ?', [$id]);
$history = db_all('SELECT h.*, a.name admin_name FROM alteration_status_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE h.request_id = ? ORDER BY h.id DESC', [$id]);
$wa = preg_replace('/\D/', '', $r['phone']);
if (str_starts_with($wa, '0')) $wa = '92' . substr($wa, 1);
admin_header('Alteration ' . $r['reference']);
?>
<div class="d-flex gap-2 align-items-center mb-3"><?= alteration_status_badge($r['status']) ?><span class="small text-muted">Received <?= e(format_datetime($r['created_at'])) ?></span>
    <a class="btn btn-sm btn-success ms-auto" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Assalam o Alaikum ' . $r['name'] . ', regarding your alteration request ' . $r['reference'] . ' at ' . store_name() . ': ') ?>"><i class="bi bi-whatsapp"></i> WhatsApp customer</a>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(alteration_link($r)) ?>" target="_blank"><i class="bi bi-eye"></i> Customer view</a></div>
<div class="row g-4"><div class="col-xl-8">
    <div class="card a-card"><div class="card-header">Requested services</div><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Garment</th><th>Service</th><th>Qty</th><th>Estimate</th><th>Time</th></tr></thead><tbody>
        <?php foreach ($items as $it): ?><tr><td><?= e($it['garment']) ?></td><td><?= e($it['service_name']) ?></td><td><?= (int) $it['qty'] ?></td><td class="small"><?= e(alteration_price_range($it['price_min'] * $it['qty'], $it['price_max'] * $it['qty'])) ?></td><td class="small"><?= (int) $it['days_max'] ?> days</td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="card-body small"><strong>Estimate shown to customer:</strong> <?= e(alteration_price_range((float) $r['est_min'], (float) $r['est_max'])) ?><?= (float) $r['pickup_fee'] > 0 ? ' + pickup ' . e(money($r['pickup_fee'])) : '' ?> · <?= e(alteration_days_range((int) $r['est_days_min'], (int) $r['est_days_max'])) ?>
        <?php if ($r['notes']): ?><div class="a-help mt-2"><strong>Customer notes:</strong> <?= nl2br(e($r['notes'])) ?></div><?php endif; ?>
        <?php if ($photos): ?><div class="d-flex gap-2 flex-wrap mt-3"><?php foreach ($photos as $ph): ?><a href="<?= e(img_url($ph['image'])) ?>" target="_blank"><img src="<?= e(img_url($ph['image'])) ?>" width="120" height="150" style="object-fit:cover;border-radius:4px" alt=""></a><?php endforeach; ?></div><?php endif; ?>
    </div></div>
    <div class="card a-card"><div class="card-header">Timeline</div><ul class="list-group list-group-flush">
        <?php foreach ($history as $h): ?><li class="list-group-item small"><span class="text-muted"><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['admin_name'] ?? 'Customer / system') ?></span> <?= (int) $h['is_customer_visible'] ? '' : '<span class="badge text-bg-secondary">internal</span>' ?><br><?= $h['status'] ? alteration_status_badge($h['status']) . ' ' : '' ?><?= e($h['note']) ?></li><?php endforeach; ?>
    </ul></div>
</div><div class="col-xl-4">
    <div class="card a-card"><div class="card-header">Customer</div><div class="card-body small">
        <strong><?= e($r['name']) ?></strong><br><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?= $r['email'] ? '<br><a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?><br><?= e($r['city']) ?>
        <hr><strong><?= $r['service_method'] === 'pickup' ? 'Courier pickup & return' : 'Customer drops off at store' ?></strong><?= $r['address'] ? '<br>' . e($r['address']) : '' ?>
        <?= $r['preferred_date'] ? '<br>Preferred date: ' . e(format_date($r['preferred_date'], 'D j M Y')) : '' ?>
    </div></div>
    <div class="card a-card"><div class="card-header">Quote &amp; status</div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
            <?= a_input('quoted_price', 'Final price (after inspection)', $r['quoted_price'], 'number', ['min' => 0, 'step' => '1'], 'Setting a price moves a new request to “Quote sent”.') ?>
            <?= a_input('ready_date', 'Ready date', $r['ready_date'], 'date') ?>
            <?= a_select('status', 'Status', alteration_statuses(), $r['status']) ?>
            <?= a_select('payment_status', 'Payment', ['unpaid' => 'Unpaid', 'paid' => 'Paid'], $r['payment_status']) ?>
            <?= a_textarea('note', 'Message to customer (optional)', '', 2) ?>
            <div class="form-check small"><input class="form-check-input" type="checkbox" name="visible" value="1" id="v" checked><label class="form-check-label" for="v">Show update to customer</label></div>
            <div class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="notify" value="1" id="n"<?= mail_enabled() && $r['email'] ? ' checked' : ' disabled' ?>><label class="form-check-label" for="n">Email the customer<?= $r['email'] ? '' : ' (no email given)' ?></label></div>
            <?= a_textarea('admin_notes', 'Internal notes (tailor instructions)', $r['admin_notes'], 3) ?>
            <button class="btn btn-dark w-100">Save</button>
        </form>
    </div></div>
</div></div>
<?php admin_footer();
