<?php
/** Orders placed from this device + notifications. */
require __DIR__ . '/_layout.php';
customer_session(true);
c_header('My orders', ['active' => 'orders']);
?>
<section class="c-narrow">
    <div class="d-flex align-items-center mb-3"><h1 class="c-h1 mb-0">My orders</h1><button class="c-btn c-btn-sm c-btn-light ms-auto" onclick="PH.enableNotifications()"><i class="bi bi-bell"></i> Alerts</button></div>
    <div data-orders><div class="c-card c-center c-muted"><span class="spinner-border spinner-border-sm"></span> Loading…</div></div>
    <div class="c-card"><div class="c-card-title"><i class="bi bi-search"></i> Order from another phone?</div>
        <form class="row g-2" action="<?= e(path('track')) ?>" method="get"><div class="col-sm-5"><input class="form-control text-uppercase" name="n" placeholder="Order number" required></div>
        <div class="col-sm-5"><input class="form-control" name="phone" placeholder="Mobile number used" inputmode="tel" required></div><div class="col-sm-2"><button class="c-btn c-btn-block">Find</button></div></form></div>
    <h2 class="c-h2 mt-4">Updates</h2><div data-notifs class="c-card p-0"></div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    PH.store.set('has_session', 1);
    var box = document.querySelector('[data-orders]'), nb = document.querySelector('[data-notifs]');
    function load() {
        PH.api('GET', '/customer/orders').then(function (r) {
            if (!r.status) { box.innerHTML = '<div class="c-notice">' + PH.esc(r.message) + '</div>'; return; }
            if (!r.data.orders.length) { box.innerHTML = '<div class="c-card c-center"><div class="c-empty-icon"><i class="bi bi-receipt"></i></div><p class="fw-bold mb-1">No orders yet</p><p class="c-muted small">Scan a shop QR code to start printing.</p></div>'; return; }
            box.innerHTML = r.data.orders.map(function (o) {
                return '<a class="c-card d-block text-reset" href="' + PH.esc(o.tracking_url) + '"><div class="d-flex align-items-start gap-2"><div class="flex-grow-1"><strong>' + PH.esc(o.order_number) + '</strong><div class="small c-muted">' + PH.esc(o.shop_name) + ' · ' + new Date(o.created_at.replace(' ', 'T')).toLocaleString() + '</div></div>'
                    + '<span class="c-chip">' + PH.esc(o.status_label) + '</span></div><div class="mt-2 fw-bold">' + (o.amount !== null ? PH.money(o.amount) : '<span class="c-muted fw-normal small">Waiting for price</span>') + '</div></a>';
            }).join('');
        });
        PH.api('GET', '/customer/notifications?limit=15').then(function (r) {
            if (!r.status) { return; }
            nb.innerHTML = r.data.items.length ? r.data.items.map(function (n) { return '<a class="d-block px-3 py-3 border-bottom text-reset' + (n.is_read ? '' : ' fw-bold') + '" href="' + PH.esc(n.link || '#') + '">' + PH.esc(n.title) + (n.body ? '<div class="small c-muted fw-normal">' + PH.esc(n.body) + '</div>' : '') + '</a>'; }).join('') : '<p class="c-muted small p-3 mb-0">No updates yet.</p>';
            if (r.data.unread) { PH.api('POST', '/customer/notifications/read', {}); }
        });
    }
    load();
    document.addEventListener('ph:notifications', load);
});
</script>
<?php c_footer([], ['active' => 'orders']);
