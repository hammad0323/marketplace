<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
$marketplaceSlug = mp_find_marketplace_type($vendor['marketplace_type_id'])['slug'];
$productCount = count(mp_products_by_vendor($vendor['id']));
$categoryRequests = $marketplaceSlug === 'business' ? mp_vendor_category_requests_for_vendor($vendor['id']) : [];
$pendingOrderCount = count(array_filter(mp_order_items_for_vendor($vendor['id']), fn ($i) => $i['status'] === 'pending'));

$pageTitle = 'Vendor Dashboard';
$theme = 'main';
require __DIR__ . '/../templates/header.php';

$statusLabels = ['pending' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$statusClass = 'badge-' . $vendor['status'];
?>
<h1><?= mp_e($vendor['store_name']) ?></h1>
<span class="badge <?= $statusClass ?>"><?= $statusLabels[$vendor['status']] ?></span>

<?php if ($vendor['status'] === 'pending'): ?>
    <div class="content-panel" style="margin-top:1rem;">
        <strong>Your store is pending admin approval.</strong>
        <p>You can complete your profile and (if you're a Business Shop) request selling
           categories now. You won't be able to publish products or receive orders, and your
           store won't be publicly visible, until an admin approves your application.</p>
    </div>
<?php elseif ($vendor['status'] === 'rejected'): ?>
    <div class="content-panel" style="margin-top:1rem;">
        <strong>Your application was rejected.</strong>
        <p><?= mp_e($vendor['rejection_reason'] ?: 'No reason was provided.') ?></p>
        <p>You may update your profile and details below, then contact support to request another review.</p>
    </div>
<?php else: ?>
    <div class="content-panel" style="margin-top:1rem;">
        <strong>Your store is live!</strong> It is publicly visible and you can publish products.
    </div>
<?php endif; ?>

<div class="card-grid" style="margin-top:1.5rem;">
    <div class="content-panel">
        <h3>Store Profile</h3>
        <p>Biography, gallery, business info, contact details, and more.</p>
        <a class="btn btn-secondary" href="profile.php">Edit Profile</a>
    </div>

    <?php if ($marketplaceSlug === 'business'): ?>
    <div class="content-panel">
        <h3>Selling Categories</h3>
        <p><?= count($categoryRequests) ?> categor<?= count($categoryRequests) === 1 ? 'y' : 'ies' ?> requested.</p>
        <a class="btn btn-secondary" href="categories.php">Manage Categories</a>
    </div>
    <?php endif; ?>

    <div class="content-panel">
        <h3>Products</h3>
        <p><?= $productCount ?> product(s) listed.</p>
        <a class="btn btn-secondary" href="products.php">View Products</a>
        <?php if ($vendor['status'] === 'approved'): ?>
            <a class="btn" href="product-form.php">Add Product</a>
        <?php endif; ?>
    </div>

    <div class="content-panel">
        <h3>Orders</h3>
        <p><?= $pendingOrderCount ?> new order item(s) awaiting action.</p>
        <a class="btn btn-secondary" href="orders.php">View Orders</a>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
