<?php
/** Customer view of an alteration request. */
$r = alteration_for_viewer($ROUTE['params']['ref'], input('t', '', 'get'));
if (!$r) {
    flash('info', 'Enter your reference and phone number to view your request.');
    redirect('alterations/track');
}
$items = db_all('SELECT * FROM alteration_request_items WHERE request_id = ?', [(int) $r['id']]);
$photos = db_all('SELECT * FROM alteration_request_photos WHERE request_id = ?', [(int) $r['id']]);
$history = db_all('SELECT * FROM alteration_status_history WHERE request_id = ? AND is_customer_visible = 1 ORDER BY id DESC', [(int) $r['id']]);
seo_set(['title' => 'Alteration request ' . $r['reference'], 'noindex' => true]);
$steps = ['new' => 'Received', 'quoted' => 'Quoted', 'confirmed' => 'Confirmed', 'in_progress' => 'In progress', 'ready' => 'Ready', 'completed' => 'Completed'];
$order = array_keys($steps);
$cur = array_search($r['status'] === 'received' ? 'confirmed' : $r['status'], $order, true);
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?php if (!empty($_GET['new'])): ?>
        <div class="uc-confirm"><span class="uc-confirm-icon"><i class="bi bi-check2"></i></span><h1 class="uc-page-title">Request received</h1>
            <p class="lead">Your reference is <strong><?= e($r['reference']) ?></strong>. We'll contact you on <?= e($r['phone']) ?> to confirm the final price and timing.</p>
            <p class="text-muted small">Bookmark this page to follow your request.</p></div>
    <?php else: ?>
        <?= breadcrumbs_html([['Alterations', url('alterations')], [$r['reference'], null]]) ?>
        <h1 class="uc-page-title">Alteration <?= e($r['reference']) ?></h1>
    <?php endif; ?>
    <?php if ($r['status'] === 'cancelled'): ?><p><?= alteration_status_badge('cancelled') ?></p><?php else: ?>
        <ol class="uc-progress"><?php foreach ($steps as $k => $l): $i = array_search($k, $order, true); ?><li class="<?= $cur !== false && $i <= $cur ? 'is-done' : '' ?><?= $cur === $i ? ' is-current' : '' ?>"><span></span><?= e($l) ?></li><?php endforeach; ?></ol>
    <?php endif; ?>
    <div class="uc-order-grid">
        <div class="uc-card-box">
            <h2 class="h5">Services</h2>
            <ul class="list-unstyled mb-3">
                <?php foreach ($items as $it): ?><li class="d-flex justify-content-between border-bottom py-2"><span><?= e($it['garment']) ?> — <?= e($it['service_name']) ?> × <?= (int) $it['qty'] ?></span><span class="text-muted small"><?= e(alteration_price_range($it['price_min'] * $it['qty'], $it['price_max'] * $it['qty'])) ?></span></li><?php endforeach; ?>
            </ul>
            <dl class="uc-totals">
                <dt>Estimated price</dt><dd><?= e(alteration_price_range((float) $r['est_min'], (float) $r['est_max'])) ?></dd>
                <?php if ((float) $r['pickup_fee'] > 0): ?><dt>Pickup &amp; return</dt><dd><?= e(money($r['pickup_fee'])) ?></dd><?php endif; ?>
                <dt>Estimated time</dt><dd><?= e(alteration_days_range((int) $r['est_days_min'], (int) $r['est_days_max'])) ?></dd>
                <?php if ($r['quoted_price'] !== null): ?><dt class="is-total">Confirmed price</dt><dd class="is-total"><?= e(money($r['quoted_price'])) ?></dd><?php endif; ?>
                <?php if ($r['ready_date']): ?><dt>Ready by</dt><dd><strong><?= e(format_date($r['ready_date'], 'D j M Y')) ?></strong></dd><?php endif; ?>
            </dl>
            <?php if ($r['notes']): ?><p class="small"><strong>Your notes:</strong> <?= nl2br(e($r['notes'])) ?></p><?php endif; ?>
            <?php if ($photos): ?><div class="d-flex gap-2 flex-wrap"><?php foreach ($photos as $ph): ?><img src="<?= e(img_url($ph['image'])) ?>" alt="" width="80" height="100" style="object-fit:cover"><?php endforeach; ?></div><?php endif; ?>
        </div>
        <div>
            <div class="uc-card-box small">
                <strong><?= e($r['name']) ?></strong> · <?= e($r['phone']) ?><br><?= e($r['city']) ?> · <?= $r['service_method'] === 'pickup' ? 'Courier pickup: ' . e($r['address']) : 'Drop off at store' ?>
                <?php if ($r['service_method'] === 'drop_off' && setting('alteration_dropoff_note')): ?><p class="mt-2 mb-0"><i class="bi bi-geo-alt"></i> <?= e(setting('alteration_dropoff_note')) ?></p><?php endif; ?>
            </div>
            <div class="uc-card-box"><h2 class="h5">Updates</h2><ul class="uc-timeline"><?php foreach ($history as $h): ?><li><time><?= e(format_datetime($h['created_at'])) ?></time><?= $h['status'] ? alteration_status_badge($h['status']) : '' ?> <?= e($h['note']) ?></li><?php endforeach; ?></ul></div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
