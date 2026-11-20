<?php
require __DIR__ . '/_layout.php';
if (!empty($_GET['shop'])) {
    $s = shop_by_code((string) $_GET['shop']);
    if ($s) {
        redirect('shop/' . $s['shop_code']);
    }
    $notFound = true;
}
$session = customer_session(false);
$last = $session && $session['last_shop_id'] ? shop_by_id((int) $session['last_shop_id']) : null;
c_header('Print from your phone', ['description' => 'Scan your print shop’s QR code, upload your files and collect your prints. No app needed.']);
?>
<section class="c-narrow">
    <div class="c-hero mt-1">
        <span class="c-chip"><i class="bi bi-qr-code-scan"></i> No app needed</span>
        <h1>Scan. Upload. Print.</h1>
        <p>Scan the QR code at your print shop, upload your documents or photos, and collect your prints — right from your phone.</p>
    </div>
    <?php if ($last): ?><a class="c-card d-flex align-items-center gap-3 text-reset" href="<?= e(path('shop/' . $last['shop_code'])) ?>">
        <?php if ($last['logo_path']): ?><img src="<?= e(path('media/' . $last['logo_path'])) ?>" alt="" width="48" height="48" style="border-radius:12px;object-fit:contain"><?php else: ?><span class="c-brand-mark" style="width:48px;height:48px"><i class="bi bi-shop"></i></span><?php endif; ?>
        <span class="flex-grow-1"><small class="c-muted">Continue at</small><br><strong><?= e($last['name']) ?></strong></span><i class="bi bi-chevron-right"></i></a><?php endif; ?>
    <div class="c-card"><div class="c-card-title"><i class="bi bi-shop"></i> Enter a shop ID</div>
        <?php if (!empty($notFound)): ?><p class="text-danger small">We couldn’t find that shop. Check the ID printed under the QR code.</p><?php endif; ?>
        <form method="get" class="d-flex gap-2"><input class="form-control text-uppercase" name="shop" placeholder="PH-XXXXXX" maxlength="9" required autocomplete="off" aria-label="Shop ID"><button class="c-btn">Open</button></form>
        <p class="small c-muted mt-2 mb-0">The shop ID is printed under every <?= e(platform_name()) ?> QR code.</p></div>
    <div class="row g-2">
        <div class="col-6"><a class="c-card d-block text-center text-reset h-100 mb-0" href="<?= e(path('cv')) ?>"><i class="bi bi-file-person fs-2" style="color:var(--c-brand)"></i><div class="fw-bold mt-1">Make a CV</div><small class="c-muted">Free templates, PDF download</small></a></div>
        <div class="col-6"><a class="c-card d-block text-center text-reset h-100 mb-0" href="<?= e(path('track')) ?>"><i class="bi bi-truck fs-2" style="color:var(--c-brand)"></i><div class="fw-bold mt-1">Track order</div><small class="c-muted">Order number + mobile</small></a></div>
    </div>
</section>
<?php c_footer([], ['active' => 'shop']);
