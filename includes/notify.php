<?php
/**
 * Notifications: admin dashboard notifications, email (SMTP or mail()) and
 * WhatsApp Cloud API. Credentials come from includes/config.php only.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function notify_admin(string $type, string $title, string $message = '', string $link = '', ?int $refId = null): void
{
    try {
        db_insert('INSERT INTO notifications (type, title, message, link, reference_id) VALUES (?, ?, ?, ?, ?)',
            [$type, mb_substr($title, 0, 190), $message, $link ?: null, $refId]);
    } catch (Throwable $e) {
        log_error('notify_admin: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
 * EMAIL
 * ------------------------------------------------------------------ */
function send_email(string $to, string $subject, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    try {
        if (SMTP_HOST !== '') {
            return smtp_send($to, $subject, $html);
        }
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    } catch (Throwable $e) {
        log_error('send_email: ' . $e->getMessage());
        return false;
    }
}

/** Minimal SMTP client (AUTH LOGIN, STARTTLS / SSL). */
function smtp_send(string $to, string $subject, string $html): bool
{
    $host = (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST;
    $fp = @stream_socket_client($host . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP error after ' . strtok($c, ' ') . ': ' . trim($r));
        return $r;
    };
    $read();
    $ehlo = 'EHLO ' . (parse_url(site_origin(), PHP_URL_HOST) ?: 'localhost');
    $cmd($ehlo, [250]);
    if (SMTP_SECURE === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd($ehlo, [250]);
    }
    if (SMTP_USER !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(SMTP_USER), [334]);
        $cmd(base64_encode(SMTP_PASS), [235]);
    }
    $cmd('MAIL FROM:<' . MAIL_FROM . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $msg = 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html));
    $cmd($msg . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

/* ------------------------------------------------------------------
 * WHATSAPP (Meta WhatsApp Cloud API - text message)
 * ------------------------------------------------------------------ */
function send_whatsapp(string $phone, string $text): bool
{
    if (WHATSAPP_TOKEN === '' || WHATSAPP_PHONE_ID === '' || setting('whatsapp_enabled', '0') !== '1') {
        return false;
    }
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => phone_intl($phone),
        'type' => 'text',
        'text' => ['body' => mb_substr($text, 0, 4000)],
    ]);
    $ch = curl_init(rtrim(WHATSAPP_API_URL, '/') . '/' . rawurlencode(WHATSAPP_PHONE_ID) . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . WHATSAPP_TOKEN, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        log_error('WhatsApp send failed (' . $code . '): ' . substr((string) $res, 0, 300));
        return false;
    }
    return true;
}

/** Click-to-chat link used in the admin panel. */
function whatsapp_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/' . phone_intl($phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/* ------------------------------------------------------------------
 * ORDER NOTIFICATIONS
 * ------------------------------------------------------------------ */
function order_summary_text(array $o, array $items): string
{
    $lines = [];
    foreach ($items as $it) {
        $lines[] = '• ' . $it['product_name'] . ($it['variation_name'] ? ' (' . $it['variation_name'] . ')' : '') . ' x' . $it['quantity'] . ' = ' . money($it['line_total']);
    }
    $how = $o['fulfillment'] === 'pickup'
        ? 'Store pickup' . ($o['pickup_at'] ? ': ' . fmt_datetime($o['pickup_at']) : '')
        : ($o['fulfillment'] === 'walkin' ? 'Walk-in' : 'Home delivery: ' . $o['address_line'] . ', ' . $o['area'] . ', ' . $o['city']);
    return ($o['order_type'] === 'rashan' ? '🧺 Monthly Rashan Order ' : '🛒 Order ') . $o['order_number'] . "\n"
        . 'Customer: ' . $o['customer_name'] . ' (' . $o['customer_phone'] . ")\n"
        . $how . "\n\n" . implode("\n", $lines) . "\n\n"
        . 'Total: ' . money($o['total']) . ' (' . payment_method_name($o['payment_method']) . ')'
        . ($o['customer_notes'] ? "\nNotes: " . $o['customer_notes'] : '');
}

function order_email_html(array $o, array $items, string $intro): string
{
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #eee">' . e($it['product_name']) . ($it['variation_name'] ? ' <small>(' . e($it['variation_name']) . ')</small>' : '')
            . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:center">' . (int) $it['quantity'] . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:right">' . money($it['line_total']) . '</td></tr>';
    }
    return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#1f2933">'
        . '<div style="background:#0f766e;color:#fff;padding:18px 22px;border-radius:10px 10px 0 0"><strong style="font-size:20px">Fahim Store</strong></div>'
        . '<div style="border:1px solid #e5e7eb;border-top:0;padding:22px;border-radius:0 0 10px 10px">'
        . '<p>' . $intro . '</p><p><strong>Order:</strong> ' . e($o['order_number']) . '<br><strong>Status:</strong> ' . e(status_label($o['status'])) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#f3f4f6"><th style="padding:6px;text-align:left">Item</th><th>Qty</th><th style="text-align:right;padding:6px">Total</th></tr>' . $rows . '</table>'
        . '<p style="text-align:right">Subtotal: ' . money($o['subtotal']) . '<br>Discount: -' . money($o['discount_amount']) . '<br>Delivery: ' . money($o['delivery_fee'])
        . '<br><strong style="font-size:16px">Total: ' . money($o['total']) . '</strong></p>'
        . '<p><a href="' . e(abs_url('track-order/?order=' . urlencode($o['order_number']))) . '" style="background:#0f766e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Track your order</a></p>'
        . '<p style="color:#6b7280;font-size:12px">' . e(setting('store_address', '')) . ' · ' . e(setting('store_phone', '')) . '</p></div></div>';
}

/** New order: admin notification + email/WhatsApp to admin and customer. */
function order_notify_new(int $orderId): void
{
    try {
        $o = db_row('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o) return;
        $items = order_items($orderId);
        $isRashan = $o['order_type'] === 'rashan';
        $title = ($isRashan ? 'New Rashan order ' : 'New order ') . $o['order_number'] . ' - ' . $o['customer_name'];
        $msg = $o['customer_phone'] . ' · ' . ($o['fulfillment'] === 'pickup' ? 'Pickup ' . fmt_datetime($o['pickup_at']) : ($o['fulfillment'] === 'walkin' ? 'Walk-in' : 'Delivery: ' . $o['area'] . ', ' . $o['city']))
            . ' · ' . count($items) . ' items · ' . money($o['total']);
        if ($o['channel'] === 'web' || $o['status'] !== 'completed') {
            notify_admin($isRashan ? 'rashan_order' : 'new_order', $title, $msg, 'order-view.php?id=' . $orderId, $orderId);
        }
        if ($o['channel'] !== 'web') return; // POS: staff already know
        $text = order_summary_text($o, $items);
        if ($adminEmail = setting('notify_email', setting('store_email', ''))) {
            send_email($adminEmail, $title, order_email_html($o, $items, 'A new order has been placed on the website.') . '<pre style="font-family:inherit">' . e($text) . '</pre>');
        }
        if ($adminWa = setting('notify_whatsapp', '')) {
            send_whatsapp($adminWa, $text);
        }
        if ($o['customer_email']) {
            send_email($o['customer_email'], 'Order received - ' . $o['order_number'], order_email_html($o, $items, 'Dear ' . e($o['customer_name']) . ', thank you for shopping with Fahim Store! We have received your order.'));
        }
        if (setting('whatsapp_customer_updates', '0') === '1') {
            send_whatsapp($o['customer_phone'], "Assalam-o-Alaikum {$o['customer_name']}! Your Fahim Store order {$o['order_number']} has been received. Total: " . money($o['total']) . '. We will update you soon.');
        }
    } catch (Throwable $e) {
        log_error('order_notify_new: ' . $e->getMessage());
    }
}

function order_notify_status(int $orderId): void
{
    try {
        $o = db_row('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o || $o['channel'] === 'pos' && $o['fulfillment'] === 'walkin') return;
        $messages = [
            'confirmed' => 'Your order %s has been confirmed.',
            'processing' => 'We are preparing your order %s.',
            'ready_for_pickup' => 'Good news! Your order %s is READY for pickup at Fahim Store.',
            'out_for_delivery' => 'Your order %s is out for delivery.',
            'completed' => 'Your order %s is completed. Thank you for shopping with Fahim Store!',
            'cancelled' => 'Your order %s has been cancelled. Please contact us if you have questions.',
        ];
        if (!isset($messages[$o['status']])) return;
        $text = sprintf($messages[$o['status']], $o['order_number']);
        if ($o['customer_email']) {
            send_email($o['customer_email'], 'Order ' . $o['order_number'] . ' - ' . status_label($o['status']), order_email_html($o, order_items($orderId), e($text)));
        }
        if (setting('whatsapp_customer_updates', '0') === '1') {
            send_whatsapp($o['customer_phone'], $text);
        }
    } catch (Throwable $e) {
        log_error('order_notify_status: ' . $e->getMessage());
    }
}
