<?php
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/order-filters.php';
require_admin('orders.export');
[$where, $params] = order_filters();
$rows = db_all("SELECT o.*, a.address_line1, a.address_line2, a.city, a.region, a.postal_code,
    (SELECT GROUP_CONCAT(CONCAT(oi.quantity, ' x ', oi.product_name, IFNULL(CONCAT(' (', oi.variant_label, ')'), '')) SEPARATOR '; ') FROM order_items oi WHERE oi.order_id = o.id) items
    FROM orders o LEFT JOIN order_addresses a ON a.order_id = o.id AND a.address_type = 'shipping' WHERE $where ORDER BY o.created_at DESC LIMIT 20000", $params);
audit_log('orders_exported', 'order', null, ['count' => count($rows), 'filters' => $_GET]);
csv_download('orders-' . date('Ymd-His') . '.csv',
    ['Order', 'Date', 'Status', 'Payment method', 'Payment status', 'Customer', 'Email', 'Phone', 'Address', 'City', 'Region', 'Postal code', 'Items', 'Subtotal', 'Discount', 'Shipping', 'COD fee', 'Gift wrap', 'Total', 'Refunded', 'Coupon', 'Courier', 'Tracking'],
    array_map(fn($o) => [$o['order_number'], $o['created_at'], $o['status'], $o['payment_method'], $o['payment_status'], $o['customer_name'], $o['email'], $o['phone'],
        trim($o['address_line1'] . ' ' . $o['address_line2']), $o['city'], $o['region'], $o['postal_code'], $o['items'], $o['subtotal'], $o['discount_total'], $o['shipping_total'],
        $o['cod_fee'], $o['gift_wrap_total'], $o['grand_total'], $o['refunded_total'], $o['coupon_code'], $o['courier_name'], $o['tracking_number']], $rows));
