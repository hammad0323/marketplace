<?php
require_once __DIR__ . '/mailer.php';

function email_wrap($title, $bodyHtml) {
    $store = get_setting('store_name');
    $accent = get_setting('theme_accent_color', '#a5763f');
    $ink = get_setting('theme_dark_color', '#211d17');
    return '<!doctype html><html><body style="margin:0;background:#f5f2ec;font-family:Arial,Helvetica,sans-serif;color:' . e($ink) . ';">'
        . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
        . '<div style="background:' . e($ink) . ';padding:20px 24px;border-radius:8px 8px 0 0;">'
        . '<span style="font-size:20px;font-weight:bold;color:#fff;">' . e($store) . '</span>'
        . '</div>'
        . '<div style="background:#fff;padding:28px 24px;border:1px solid #eee;border-top:none;">'
        . '<h2 style="margin-top:0;color:' . e($ink) . ';">' . e($title) . '</h2>'
        . $bodyHtml
        . '</div>'
        . '<div style="text-align:center;padding:16px;color:#999;font-size:12px;">'
        . '&copy; ' . date('Y') . ' ' . e($store) . '. All rights reserved.'
        . '</div>'
        . '</div></body></html>';
}

function email_button($text, $url) {
    $accent = get_setting('theme_accent_color', '#a5763f');
    return '<p style="margin:24px 0;"><a href="' . e($url) . '" style="background:' . e($accent) . ';color:#fff;padding:12px 28px;text-decoration:none;border-radius:3px;display:inline-block;">' . e($text) . '</a></p>';
}

function order_items_table($items) {
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;">' . e($it['product_name']) . ($it['variation_label'] ? ' (' . e($it['variation_label']) . ')' : '') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . (int)$it['qty'] . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">' . format_price($it['subtotal']) . '</td>'
            . '</tr>';
    }
    return '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #ddd;">Item</th><th style="padding:8px;border-bottom:2px solid #ddd;">Qty</th><th style="text-align:right;padding:8px;border-bottom:2px solid #ddd;">Subtotal</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>';
}

function send_order_confirmation_email($mysqli, $order, $items) {
    if (get_setting('order_emails_enabled', '1') !== '1') return;
    $email = $order['guest_email'];
    if (!$email) return;
    $body = '<p>Hi ' . e($order['guest_name']) . ',</p>'
        . '<p>Thank you for your order! We have received order <strong>' . e($order['order_number']) . '</strong> and will notify you as it progresses.</p>'
        . order_items_table($items)
        . '<p style="text-align:right;font-size:16px;"><strong>Total: ' . format_price($order['total']) . '</strong></p>'
        . '<p>Payment Method: <strong>' . e(strtoupper($order['payment_method'])) . '</strong></p>'
        . email_button('View Order', url('invoice/' . $order['id']));
    send_email($email, 'Order Confirmation - ' . $order['order_number'], email_wrap('Order Confirmed', $body), $order['guest_name']);
}

function send_admin_new_order_email($order) {
    if (get_setting('order_emails_enabled', '1') !== '1') return;
    $adminEmail = get_setting('store_email');
    if (!$adminEmail) return;
    $body = '<p>A new order has been placed on your store.</p>'
        . '<p><strong>Order:</strong> ' . e($order['order_number']) . '<br>'
        . '<strong>Customer:</strong> ' . e($order['guest_name']) . ' (' . e($order['guest_email']) . ')<br>'
        . '<strong>Total:</strong> ' . format_price($order['total']) . '<br>'
        . '<strong>Payment:</strong> ' . e(strtoupper($order['payment_method'])) . '</p>'
        . email_button('View in Admin', BASE_URL . '/admin/order_view.php?id=' . $order['id']);
    send_email($adminEmail, 'New Order: ' . $order['order_number'], email_wrap('New Order Received', $body));
}

function send_order_status_email($order, $newStatus, $note = '') {
    if (get_setting('order_emails_enabled', '1') !== '1') return;
    $email = $order['guest_email'] ?: null;
    if (!$email) return;
    $label = ucwords(str_replace('_', ' ', $newStatus));
    $body = '<p>Hi ' . e($order['guest_name']) . ',</p>'
        . '<p>Your order <strong>' . e($order['order_number']) . '</strong> status has been updated to:</p>'
        . '<p style="font-size:18px;"><strong>' . e($label) . '</strong></p>'
        . ($note ? '<p>' . e($note) . '</p>' : '')
        . email_button('View Order', url('invoice/' . $order['id']));
    send_email($email, 'Order Update - ' . $order['order_number'], email_wrap('Order Status Updated', $body), $order['guest_name']);
}

function send_welcome_email($customer) {
    $body = '<p>Hi ' . e($customer['name']) . ',</p>'
        . '<p>Welcome to ' . e(get_setting('store_name')) . '! Your account has been created successfully.</p>'
        . '<p>Start exploring our latest collections and enjoy a premium shopping experience.</p>'
        . email_button('Start Shopping', url('shop'));
    send_email($customer['email'], 'Welcome to ' . get_setting('store_name'), email_wrap('Welcome!', $body), $customer['name']);
}

function send_contact_email($name, $email, $message) {
    $adminEmail = get_setting('store_email');
    if (!$adminEmail) return false;
    $body = '<p><strong>From:</strong> ' . e($name) . ' (' . e($email) . ')</p>'
        . '<p><strong>Message:</strong></p><p>' . nl2br(e($message)) . '</p>';
    return send_email($adminEmail, 'Contact Form: ' . $name, email_wrap('New Contact Message', $body));
}
