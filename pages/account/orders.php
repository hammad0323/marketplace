<?php
$c = require_customer();
$number = $ROUTE['params']['number'] ?? null;
if ($number) {
    $o = db_row('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?', [$number, (int) $c['id']]);
    if (!$o) {
        route_not_found($ROUTE['path']);
    }
    redirect('order/' . $o['order_number'] . '?t=' . $o['access_token']);
}
seo_set(['title' => 'My Orders', 'noindex' => true]);
$total = (int) db_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int) $c['id']]);
$pg = paginate($total, 15);
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset'], [(int) $c['id']]);
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">My Orders</h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'orders']); ?>
        <div class="uc-account-main">
            <div class="uc-card-box"><?php partial('components/order-table', ['orders' => $orders]); ?><?= pagination_links($pg, url('account/orders')) ?></div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
