<?php $hours = $profile['business_hours'] ?? []; ?>

<section class="business-shop-header">
    <img class="shop-logo" src="<?= e($profile['logo_image'] ?? '/assets/img/placeholder.svg') ?>" alt="<?= e($vendor['store_name']) ?> logo">
    <div>
        <span class="badge">🏪 Business Shop</span>
        <?php if ($vendor['is_verified']): ?><span class="verified-badge">✔ Verified Business</span><?php endif; ?>
        <h1 style="margin:0.25rem 0;"><?= e($vendor['store_name']) ?></h1>
        <p style="margin:0;">⭐ <?= $rating['average'] ?: 'No ratings yet' ?> <?= $rating['total'] ? "({$rating['total']} reviews)" : '' ?></p>
    </div>
</section>

<?php if (!empty($profile['business_info'])): ?>
    <div class="content-panel">
        <h2>About This Shop</h2>
        <p><?= nl2br(e($profile['business_info'])) ?></p>
    </div>
<?php endif; ?>

<div class="content-panel" style="display:flex; gap:2rem; flex-wrap:wrap;">
    <?php if (!empty($profile['contact_email']) || !empty($profile['contact_phone']) || !empty($profile['contact_address'])): ?>
    <div>
        <h3>Contact</h3>
        <?php if (!empty($profile['contact_email'])): ?><p><?= e($profile['contact_email']) ?></p><?php endif; ?>
        <?php if (!empty($profile['contact_phone'])): ?><p><?= e($profile['contact_phone']) ?></p><?php endif; ?>
        <?php if (!empty($profile['contact_address'])): ?><p><?= e($profile['contact_address']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($hours): ?>
    <div>
        <h3>Business Hours</h3>
        <?php foreach ($hours as $day => $range): ?>
            <p><strong><?= e(ucfirst($day)) ?>:</strong> <?= e($range) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($profile['delivery_info'])): ?>
    <div>
        <h3>Delivery Information</h3>
        <p><?= nl2br(e($profile['delivery_info'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($profile['shop_policies'])): ?>
    <div>
        <h3>Shop Policies</h3>
        <p><?= nl2br(e($profile['shop_policies'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<h2 class="business-section-title">Products</h2>
<div class="card-grid">
    <?php foreach ($products as $product): ?>
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>This shop hasn't listed any products yet.</p><?php endif; ?>
</div>
