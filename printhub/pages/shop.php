<?php
/** Shop landing page — what customers see after scanning the shop QR code. */
require __DIR__ . '/_layout.php';
$shop = shop_by_code((string) $params['code']);
if (!$shop || $shop['status'] === 'deactivated') {
    http_response_code(404);
    c_header('Shop not found');
    echo '<section class="c-narrow c-center py-5"><div class="c-empty-icon"><i class="bi bi-shop"></i></div><h1 class="c-h1">Shop not found</h1><p class="c-muted">Please scan the QR code again or check the shop ID.</p><a class="c-btn" href="' . e(path('')) . '">Home</a></section>';
    c_footer();
    return;
}
if (strtoupper((string) $params['code']) !== $params['code']) {
    redirect('shop/' . $shop['shop_code']);
}
$session = customer_session(true);
db_exec('UPDATE customer_sessions SET last_shop_id = ? WHERE id = ?', [(int) $shop['id'], (int) $session['id']]);
$p = shop_public($shop);
$cats = shop_catalog((int) $shop['id']);
$notes = array_column(db_all('SELECT category_id, note FROM shop_services WHERE shop_id = ? AND subcategory_id IS NULL AND note IS NOT NULL', [(int) $shop['id']]), 'note', 'category_id');
$wa = preg_replace('/\D/', '', (string) ($shop['whatsapp'] ?: $shop['phone']));
if (str_starts_with($wa, '0')) $wa = '92' . substr($wa, 1);
$wifi = $p['wifi'];
c_header($shop['name'], ['shop' => $shop, 'theme' => $p['theme_color'], 'description' => 'Upload files to print at ' . $shop['name'] . ', ' . $shop['city'] . '.', 'active' => 'shop']);
?>
<section class="c-narrow">
    <div class="c-hero">
        <div class="d-flex align-items-center gap-3"><?php if ($p['logo_url']): ?><img class="c-hero-logo" src="<?= e($p['logo_url']) ?>" alt=""><?php endif; ?>
            <div><span class="c-chip <?= $p['is_open'] ? '' : '' ?>"><i class="bi bi-circle-fill" style="font-size:.5rem;color:<?= $p['is_open'] ? '#4ADE80' : '#FCA5A5' ?>"></i> <?= e($p['status_label']) ?><?= $p['is_open'] ? ' · ready in ~' . (int) $p['estimated_minutes'] . ' min' : '' ?></span></div></div>
        <h1><?= e($p['heading']) ?></h1>
        <p><?= e($p['message'] ?: 'Upload your documents or photos and collect your prints at ' . $shop['name'] . '.') ?></p>
        <?php if ($p['is_open']): ?><a class="c-btn" href="<?= e(path('shop/' . $shop['shop_code'] . '/print')) ?>"><i class="bi bi-printer"></i> Start printing</a>
        <?php else: ?><div class="c-notice mt-3 mb-0" style="background:rgba(255,255,255,.95)"><?= e($p['status_message']) ?></div><?php endif; ?>
    </div>

    <?php if ($wifi): ?>
    <div class="c-card c-wifi"><div class="c-card-title"><i class="bi bi-wifi"></i> Connect to our Wi-Fi</div>
        <div class="d-flex gap-3 flex-wrap align-items-start"><div class="flex-grow-1" style="min-width:200px">
            <div class="c-wifi-row"><small class="c-muted">Network</small><code><?= e($wifi['ssid']) ?></code><button class="c-btn c-btn-sm c-btn-light" data-copy="<?= e($wifi['ssid']) ?>"><i class="bi bi-copy"></i></button></div>
            <?php if ($wifi['password'] !== ''): ?><div class="c-wifi-row"><small class="c-muted">Password</small><code><?= e($wifi['password']) ?></code><button class="c-btn c-btn-sm c-btn-light" data-copy="<?= e($wifi['password']) ?>"><i class="bi bi-copy"></i></button></div>
            <?php else: ?><p class="small c-muted mb-2">Open network — no password needed.</p><?php endif; ?>
            <p class="small c-muted mb-0">Scan the Wi-Fi code with your camera to join (most Android phones and iPhones), or copy the password.</p></div>
            <img class="c-wifi-qr" src="<?= e(path('api/v1/shops/' . $shop['shop_code'] . '/wifi.png')) ?>" alt="Wi-Fi QR code" loading="lazy"></div>
    </div>
    <?php endif; ?>

    <?php if ($cats): ?>
    <h2 class="c-h2 mt-4">What would you like to print?</h2>
    <div class="c-services">
    <?php foreach ($cats as $c): ?>
        <a class="c-service" href="<?= e(path('shop/' . $shop['shop_code'] . '/print?category=' . $c['id'])) ?>"<?= $p['is_open'] ? '' : ' aria-disabled="true"' ?>>
            <?php if ($c['icon_url']): ?><img src="<?= e($c['icon_url']) ?>" alt=""><?php else: ?><i class="bi bi-<?= e($c['icon']) ?>"></i><?php endif; ?>
            <strong><?= e($c['name']) ?></strong><small><?= e($notes[$c['id']] ?? $c['description']) ?></small></a>
    <?php endforeach; ?>
        <a class="c-service" href="<?= e(path('cv?shop=' . $shop['shop_code'])) ?>"><i class="bi bi-file-person"></i><strong>Make a CV</strong><small>Free templates — print it here</small></a>
    </div>
    <?php endif; ?>

    <div class="c-card mt-3"><div class="c-card-title"><i class="bi bi-info-circle"></i> Shop details</div>
        <div class="c-info-row"><i class="bi bi-geo-alt"></i><div><?= e($shop['address']) ?><?= $shop['area'] ? ', ' . e($shop['area']) : '' ?>, <?= e($shop['city']) ?>
            <?php if ($shop['latitude']): ?><br><a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?= e($shop['latitude'] . ',' . $shop['longitude']) ?>">Get directions</a><?php endif; ?></div></div>
        <div class="c-info-row"><i class="bi bi-telephone"></i><div><a href="tel:<?= e($shop['phone']) ?>"><?= e($shop['phone']) ?></a><?php if ($wa): ?> · <a target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>">WhatsApp</a><?php endif; ?></div></div>
        <?php if ($p['opening_hours']): ?><div class="c-info-row"><i class="bi bi-clock"></i><div><?= e($p['opening_hours']) ?></div></div><?php endif; ?>
        <div class="c-info-row"><i class="bi bi-wallet2"></i><div><?= $p['payment_mode'] === 'pay_at_shop' ? 'Pay at the shop when you collect' : ($p['payment_mode'] === 'advance_required' ? 'Advance payment required before printing' : 'Pay in advance or at the shop') ?> · <?= $p['pricing_mode'] === 'auto' ? 'Prices shown before you order' : 'The shop sends you the price' ?></div></div>
    </div>
    <p class="c-privacy"><i class="bi bi-shield-lock"></i> Your files are private to this shop and deleted automatically <?= (int) $p['retention_days'] ?> day<?= $p['retention_days'] > 1 ? 's' : '' ?> after your order is closed. <a href="<?= e(path('privacy')) ?>">Privacy</a></p>
</section>
<?php c_footer([], ['active' => 'shop']);
