<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "
    SELECT dp.*,
        d.slug AS doctor_slug, d.is_premium, d.verification_status AS doctor_verification_status, d.rating_avg AS doctor_rating_avg, d.rating_count AS doctor_rating_count,
        du.full_name AS doctor_name, du.avatar AS doctor_avatar,
        ph.slug AS pharmacy_slug, ph.verification_status AS pharmacy_verification_status, ph.store_name, ph.rating_avg AS pharmacy_rating_avg, ph.rating_count AS pharmacy_rating_count,
        phu.avatar AS pharmacy_avatar,
        pc.name AS category_name
    FROM doctor_products dp
    LEFT JOIN doctors d ON d.id = dp.doctor_id AND dp.seller_type = 'doctor'
    LEFT JOIN users du ON du.id = d.user_id
    LEFT JOIN pharmacies ph ON ph.id = dp.pharmacy_id AND dp.seller_type = 'pharmacy'
    LEFT JOIN users phu ON phu.id = ph.user_id
    LEFT JOIN product_categories pc ON pc.id = dp.category_id
    WHERE dp.slug = ? LIMIT 1
");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$product = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$sellerOk = $product && (
    ($product['seller_type'] === 'doctor' && $product['is_premium'] && $product['doctor_verification_status'] === 'verified')
    || ($product['seller_type'] === 'pharmacy' && $product['pharmacy_verification_status'] === 'verified')
);
if (!$product || !$product['is_active'] || !$sellerOk) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isPharmacySeller = $product['seller_type'] === 'pharmacy';
$sellerName = $isPharmacySeller ? $product['store_name'] : $product['doctor_name'];
$sellerAvatar = $isPharmacySeller ? $product['pharmacy_avatar'] : $product['doctor_avatar'];
$sellerUrl = $isPharmacySeller ? pharmacy_url($product['pharmacy_slug']) : doctor_url($product['doctor_slug']);
$sellerRatingAvg = $isPharmacySeller ? $product['pharmacy_rating_avg'] : $product['doctor_rating_avg'];
$sellerRatingCount = $isPharmacySeller ? $product['pharmacy_rating_count'] : $product['doctor_rating_count'];

$patient = null;
if (is_logged_in() && current_role() === 'patient') {
    $stmt = mysqli_prepare(db(), 'SELECT p.*, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1');
    $pid = current_profile_id();
    mysqli_stmt_bind_param($stmt, 'i', $pid);
    mysqli_stmt_execute($stmt);
    $patient = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
}

$sellerColumn = $isPharmacySeller ? 'pharmacy_id' : 'doctor_id';
$sellerId = $isPharmacySeller ? $product['pharmacy_id'] : $product['doctor_id'];
$related = mysqli_query(db(), "
    SELECT id, slug, name, price, type, image FROM doctor_products
    WHERE $sellerColumn = " . (int) $sellerId . " AND seller_type = '" . ($isPharmacySeller ? 'pharmacy' : 'doctor') . "' AND is_active = 1 AND id != " . (int) $product['id'] . "
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = ($product['meta_title'] ?: ($product['name'] . ' — ' . SITE_NAME));
$metaDescription = $product['meta_description'] ?: excerpt($product['description'] ?: $product['name'], 155);
$ogImage = $product['image'] ? APP_URL . '/uploads/' . $product['image'] : null;
$canonical = APP_URL . product_url($product['slug']);
$extraHead = '<script type="application/ld+json">' . json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => $product['type'] === 'service' ? 'Service' : 'Product',
    'name' => $product['name'],
    'description' => strip_tags($product['description'] ?: ''),
    'image' => $ogImage,
    'offers' => [
        '@type' => 'Offer', 'price' => $product['price'], 'priceCurrency' => 'USD',
        'availability' => ($product['type'] === 'product' && (int) $product['stock'] <= 0) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
    ],
    'provider' => ['@type' => $isPharmacySeller ? 'Pharmacy' : 'Physician', 'name' => $sellerName],
])) . '</script>';
$extraScripts = '<script src="/assets/js/product-checkout.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 40px);">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">Home</a> <i class="ri-arrow-right-s-line"></i>
            <a href="/products">Products &amp; Services</a> <i class="ri-arrow-right-s-line"></i>
            <span><?= e($product['name']) ?></span>
        </nav>

        <div class="split-sidebar-right" style="gap:32px;">
            <div>
                <div class="card" style="padding:0;overflow:hidden;margin-bottom:24px;" data-reveal>
                    <?php if ($product['image']): ?>
                    <img src="/uploads/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" style="width:100%;max-height:420px;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:280px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:52px;"><i class="<?= $product['type'] === 'service' ? 'ri-heart-pulse-line' : 'ri-capsule-line' ?>"></i></div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:32px;" data-reveal>
                    <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                        <span class="badge badge-<?= $product['type'] === 'service' ? 'pending' : 'verified' ?>"><?= $product['type'] === 'service' ? 'Service' : 'Product' ?></span>
                        <?php if ($product['category_name']): ?><span class="badge badge-free"><?= e($product['category_name']) ?></span><?php endif; ?>
                    </div>
                    <h1 style="margin-bottom:10px;font-size:28px;"><?= e($product['name']) ?></h1>
                    <p style="color:var(--color-text-muted);line-height:1.8;white-space:pre-line;"><?= e($product['description']) ?></p>

                    <div class="divider-fade"></div>
                    <h2 style="font-size:16px;margin-bottom:14px;">Offered by</h2>
                    <a href="<?= e($sellerUrl) ?>" class="card" style="padding:16px;display:flex;align-items:center;gap:12px;">
                        <img src="<?= e(avatar_url($sellerAvatar, $sellerName)) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        <div>
                            <strong style="display:block;"><?= e($sellerName) ?></strong>
                            <?php if ($sellerRatingCount > 0): ?><span style="font-size:12.5px;color:var(--color-text-muted);"><i class="ri-star-fill" style="color:var(--color-warning);"></i> <?= number_format($sellerRatingAvg, 1) ?> (<?= (int) $sellerRatingCount ?> reviews)</span><?php endif; ?>
                        </div>
                    </a>
                </div>

                <?php if ($related): ?>
                <h3 style="margin:28px 0 14px;">More from this <?= $isPharmacySeller ? 'pharmacy' : 'doctor' ?></h3>
                <div class="grid grid-2 stagger">
                    <?php foreach ($related as $r): ?>
                    <a href="<?= e(product_url($r['slug'])) ?>" class="card card-hover" style="padding:14px;display:flex;gap:12px;align-items:center;" data-reveal>
                        <?php if ($r['image']): ?><img src="/uploads/<?= e($r['image']) ?>" style="width:52px;height:52px;border-radius:10px;object-fit:cover;flex-shrink:0;"><?php else: ?>
                        <div style="width:52px;height:52px;border-radius:10px;background:var(--gradient-primary);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;"><i class="<?= $r['type'] === 'service' ? 'ri-heart-pulse-line' : 'ri-capsule-line' ?>"></i></div>
                        <?php endif; ?>
                        <div style="min-width:0;">
                            <strong style="display:block;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['name']) ?></strong>
                            <span style="font-size:13px;color:var(--color-primary);font-weight:700;"><?= format_currency($r['price']) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="position:sticky;top:calc(var(--header-height) + 20px);">
                <div class="card" style="padding:24px;" data-reveal="right" id="checkout-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="font-size:26px;color:var(--color-primary);"><?= format_currency($product['price']) ?></strong>
                        <?php if ($product['type'] === 'product'): ?>
                            <?php if ((int) $product['stock'] > 0): ?><span style="font-size:12.5px;color:var(--color-success);font-weight:600;"><?= (int) $product['stock'] ?> in stock</span>
                            <?php else: ?><span style="font-size:12.5px;color:var(--color-danger);font-weight:600;">Out of stock</span><?php endif; ?>
                        <?php else: ?>
                        <span style="font-size:12.5px;color:var(--color-text-muted);"><?= e($product['duration_label'] ?: '') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="divider-fade" style="margin:16px 0;"></div>

                    <?php if (!is_logged_in()): ?>
                        <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:14px;">Log in as a patient to request this <?= $product['type'] ?>.</p>
                        <a href="#" class="btn btn-primary btn-block" data-requires-auth data-action-url="<?= e(product_url($slug)) ?>">Log In to Purchase</a>
                    <?php elseif (current_role() !== 'patient'): ?>
                        <p style="color:var(--color-text-muted);font-size:13.5px;">Only patient accounts can purchase from the storefront.</p>
                    <?php elseif ($product['type'] === 'product' && (int) $product['stock'] <= 0): ?>
                        <button type="button" class="btn btn-outline btn-block" disabled>Out of Stock</button>
                    <?php else: ?>
                        <form id="checkout-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <?php if ($product['type'] === 'product'): ?>
                            <div class="form-group">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" id="checkout-qty" value="1" min="1" max="<?= (int) $product['stock'] ?>" step="1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Shipping Address</label>
                                <textarea class="form-control" name="shipping_address" rows="2" placeholder="Where should this be delivered?"><?= e(trim(($patient['address'] ?? '') . ($patient['city'] ? ', ' . $patient['city'] : '') . ($patient['state'] ? ', ' . $patient['state'] : ''), ', ')) ?></textarea>
                            </div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" name="contact_phone" value="<?= e($patient['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Notes (optional)</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Anything the doctor should know"></textarea>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:16px;padding-top:6px;border-top:1px solid var(--color-border);">
                                <span style="color:var(--color-text-muted);">Total</span>
                                <strong id="checkout-total" data-unit-price="<?= e($product['price']) ?>"><?= format_currency($product['price']) ?></strong>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" id="place-order-btn">Place Order</button>
                        </form>
                        <div id="checkout-success" style="display:none;text-align:center;padding:12px 0;">
                            <i class="ri-checkbox-circle-fill" style="font-size:40px;color:var(--color-success);margin-bottom:10px;display:block;"></i>
                            <strong style="display:block;margin-bottom:6px;">Order sent!</strong>
                            <p style="color:var(--color-text-muted);font-size:13px;margin-bottom:16px;">The seller will confirm your order shortly.</p>
                            <a href="/patient/orders" rel="nofollow" class="btn btn-outline btn-block">View My Orders</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
