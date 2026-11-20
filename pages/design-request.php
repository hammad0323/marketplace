<?php
/** Customer view of a Design Studio quote request: previews, sizes, confirmed price, one-tap order. */
$r = design_request_for_viewer($ROUTE['params']['ref'], input('t', '', 'get'));
if (!$r) {
    flash('info', 'Please open the link from your confirmation message to view your design.');
    redirect('design-studio');
}
$design = db_row('SELECT * FROM designs WHERE id = ?', [(int) $r['design_id']]);
$design['spec_data'] = json_decode((string) $design['spec'], true) ?: [];
$totals = design_request_totals($r, $design);
seo_set(['title' => 'Your design ' . $r['reference'], 'noindex' => true]);
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?php if (!empty($_GET['new'])): ?>
        <div class="uc-confirm"><span class="uc-confirm-icon"><i class="bi bi-check2"></i></span><h1 class="uc-page-title">Design received</h1>
            <p class="lead">Your reference is <strong><?= e($r['reference']) ?></strong>. Our team will check your design and confirm the final price — we'll contact you on <?= e($r['phone']) ?>.</p>
            <p class="text-muted small">Bookmark this page: the price and an “Order now” button appear here as soon as it is confirmed.</p></div>
    <?php else: ?>
        <?= breadcrumbs_html([['Design Studio', url('design-studio')], [$r['reference'], null]]) ?>
        <h1 class="uc-page-title">Your design <?= e($r['reference']) ?></h1>
        <p><?= design_request_badge($r['status']) ?></p>
    <?php endif; ?>
    <div class="uc-order-grid">
        <div class="uc-card-box">
            <h2 class="h5">Design</h2>
            <div class="uc-dz-request-previews">
                <?php foreach (['preview_front' => 'Front', 'preview_back' => 'Back'] as $k => $label): if ($design[$k]): ?>
                    <figure class="m-0"><img src="<?= e(img_url($design[$k])) ?>" alt="<?= $label ?> of your design" width="420" height="490"><figcaption class="small text-muted text-center mt-1"><?= $label ?></figcaption></figure>
                <?php endif; endforeach; ?>
            </div>
            <dl class="uc-dl mt-3"><?php foreach (design_summary($design) as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
            <?php if ($r['notes']): ?><p class="small"><strong>Your notes:</strong> <?= nl2br(e($r['notes'])) ?></p><?php endif; ?>
        </div>
        <div class="uc-card-box">
            <h2 class="h5"><?= $totals['final'] ? 'Your price' : 'Estimated price' ?></h2>
            <dl class="uc-totals">
                <?php foreach ($totals['lines'] as $l): ?>
                    <dt>Size <?= e($l['size']) ?> × <?= (int) $l['qty'] ?> <small>(garment <?= e(money($l['garment'])) ?> + print <?= e(money($l['print'])) ?>)</small></dt><dd><?= e(money($l['total'])) ?></dd>
                <?php endforeach; ?>
                <dt class="is-total"><?= $totals['final'] ? 'Total' : 'Estimated total' ?></dt><dd class="is-total"><?= e(money($totals['total'])) ?></dd>
            </dl>
            <?php if ($r['admin_message']): ?><div class="uc-note mt-3"><strong>Message from <?= e(store_name()) ?>:</strong><br><?= nl2br(e($r['admin_message'])) ?></div><?php endif; ?>
            <?php if ($r['status'] === 'quoted'): ?>
                <p class="small text-muted mt-3">Delivery is calculated at checkout. Printed in <?= design_production_days() ?> working days after you order.</p>
                <button type="button" class="uc-btn uc-btn--block" data-quote-add data-ref="<?= e($r['reference']) ?>" data-t="<?= e($r['access_token']) ?>">Order now — <?= e(money($totals['total'])) ?></button>
            <?php elseif ($r['status'] === 'new'): ?>
                <p class="small text-muted mt-3"><i class="bi bi-hourglass-split"></i> Waiting for our team to confirm the price. You'll be able to order here as soon as it is confirmed.</p>
            <?php elseif ($r['status'] === 'ordered'): ?>
                <p class="small mt-3"><i class="bi bi-check-circle text-success"></i> This design has been ordered. Thank you!</p>
            <?php else: ?>
                <p class="small mt-3">This request was cancelled. <a href="<?= e(url('design-studio')) ?>">Start a new design</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$pageScripts = "<script>jQuery(function ($) { $('[data-quote-add]').on('click', function () { var \$b = $(this).addClass('is-loading').prop('disabled', true);"
    . " $.post(UC.base + 'ajax/designer.php', { action: 'quote_add', reference: \$b.data('ref'), t: \$b.data('t'), csrf_token: UC.csrf }).done(function (r) {"
    . " if (r.ok) { window.location.href = UC.base + 'cart'; } else { Swal.fire({ icon: 'warning', text: r.message }); } })"
    . ".fail(function (x) { Swal.fire({ icon: 'warning', text: (x.responseJSON && x.responseJSON.message) || 'Please try again.' }); })"
    . ".always(function () { \$b.removeClass('is-loading').prop('disabled', false); }); }); });</script>";
require UC_ROOT . '/includes/footer.php';
