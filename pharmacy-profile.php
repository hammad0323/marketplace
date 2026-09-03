<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "SELECT p.*, u.full_name, u.avatar, u.email, u.phone
    FROM pharmacies p JOIN users u ON u.id = p.user_id
    WHERE p.slug = ? AND u.status = 'active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$pharmacy = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$pharmacy || $pharmacy['verification_status'] !== 'verified') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

mysqli_query(db(), 'UPDATE pharmacies SET profile_views = profile_views + 1 WHERE id = ' . (int) $pharmacy['id']);

$stmt = mysqli_prepare(db(), "SELECT * FROM doctor_products WHERE pharmacy_id = ? AND seller_type = 'pharmacy' AND is_active = 1 ORDER BY type, name");
mysqli_stmt_bind_param($stmt, 'i', $pharmacy['id']);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = $pharmacy['store_name'] . ' — Pharmacy | ' . SITE_NAME;
$metaDescription = excerpt($pharmacy['bio'] ?: ($pharmacy['store_name'] . ' is a verified pharmacy on ' . SITE_NAME . '.'), 155);
$canonical = APP_URL . pharmacy_url($pharmacy['slug']);
$extraHead = '<script type="application/ld+json">' . json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Pharmacy', 'name' => $pharmacy['store_name'],
    'address' => $pharmacy['address'] ?: null, 'url' => $canonical,
])) . '</script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 40px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <a href="/pharmacies">Pharmacies</a> <i class="ri-arrow-right-s-line"></i> <span><?= e($pharmacy['store_name']) ?></span></nav>

        <div class="card" style="padding:32px;margin-bottom:32px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;" data-reveal>
            <img src="<?= e(avatar_url($pharmacy['avatar'], $pharmacy['store_name'])) ?>" alt="<?= e($pharmacy['store_name']) ?>" style="width:76px;height:76px;border-radius:18px;object-fit:cover;flex-shrink:0;">
            <div style="flex:1;min-width:220px;">
                <h1 style="font-size:26px;margin-bottom:6px;"><?= e($pharmacy['store_name']) ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified Pharmacy</span>
                </div>
                <p style="color:var(--color-text-muted);font-size:14px;">
                    <?php if ($pharmacy['address']): ?><i class="ri-map-pin-line"></i> <?= e($pharmacy['address']) ?><?php if ($pharmacy['city']): ?>, <?= e($pharmacy['city']) ?><?php endif; ?>
                    <?php elseif ($pharmacy['city']): ?><i class="ri-map-pin-line"></i> <?= e($pharmacy['city']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($pharmacy['bio']): ?>
        <div class="card" style="padding:28px;margin-bottom:32px;" data-reveal>
            <h3 style="font-size:16px;margin-bottom:10px;">About This Store</h3>
            <p style="color:var(--color-text-muted);line-height:1.8;white-space:pre-line;"><?= e($pharmacy['bio']) ?></p>
        </div>
        <?php endif; ?>

        <h2 style="font-size:20px;margin-bottom:18px;">Products &amp; Medicines</h2>
        <?php if (!$products): ?>
        <div class="empty-state card"><i class="ri-store-2-line"></i><h4>No listings yet</h4><p>This pharmacy hasn't added any products yet. Check back soon.</p></div>
        <?php else: ?>
        <div class="grid grid-3 stagger">
            <?php foreach ($products as $p): ?>
            <div class="card card-hover" style="padding:20px;" data-reveal>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                    <span class="badge badge-<?= $p['type'] === 'service' ? 'pending' : 'verified' ?>"><?= $p['type'] === 'service' ? 'Service' : 'Product' ?></span>
                    <strong style="color:var(--color-primary);font-size:17px;"><?= format_currency($p['price']) ?></strong>
                </div>
                <?php if ($p['image']): ?><img src="/uploads/<?= e($p['image']) ?>" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:12px;margin-bottom:10px;"><?php endif; ?>
                <strong style="display:block;margin-bottom:4px;"><?= e($p['name']) ?></strong>
                <p style="font-size:13.5px;color:var(--color-text-muted);margin-bottom:10px;"><?= e(excerpt($p['description'] ?? '', 90)) ?></p>
                <a href="<?= e(product_url($p['slug'])) ?>" class="btn btn-primary btn-block">View Details &amp; Order</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
