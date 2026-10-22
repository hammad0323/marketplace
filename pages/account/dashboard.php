<?php
$c = require_customer();
seo_set(['title' => 'My Account', 'noindex' => true]);
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 5', [(int) $c['id']]);
$stats = db_row("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN " . sql_revenue_condition('orders') . " THEN grand_total END),0) spent FROM orders WHERE customer_id = ?", [(int) $c['id']]);
$addr = db_row('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC LIMIT 1', [(int) $c['id']]);
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">Hello, <?= e($c['first_name']) ?></h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'dashboard']); ?>
        <div class="uc-account-main">
            <div class="uc-stat-row">
                <div class="uc-card-box"><span class="text-muted small">Orders</span><strong class="d-block fs-3"><?= (int) $stats['n'] ?></strong></div>
                <div class="uc-card-box"><span class="text-muted small">Wishlist</span><strong class="d-block fs-3"><?= count(wishlist_product_ids()) ?></strong></div>
                <div class="uc-card-box"><span class="text-muted small">Member since</span><strong class="d-block fs-5"><?= e(format_date($c['created_at'], 'M Y')) ?></strong></div>
            </div>
            <div class="uc-card-box">
                <div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Recent orders</h2><a href="<?= e(url('account/orders')) ?>">View all</a></div>
                <?php partial('components/order-table', ['orders' => $orders]); ?>
            </div>
            <div class="uc-card-box">
                <div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Default address</h2><a href="<?= e(url('account/addresses')) ?>">Manage</a></div>
                <?php if ($addr): ?><address class="mt-2 mb-0"><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?>, <?= e($addr['region']) ?><br><?= e($addr['phone']) ?></address>
                <?php else: ?><p class="text-muted mt-2 mb-0">No saved address yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
