<?php
/**
 * Email: SMTP (recommended — use your cPanel mailbox) with PHP mail() fallback.
 * Configure in Admin » Settings » Email. Nothing is sent when email is disabled.
 */

function mail_enabled(): bool
{
    return setting_bool('mail_enabled') && valid_email((string) setting('mail_from_email', ''));
}

function send_mail(string $to, string $subject, string $html): bool
{
    if (!mail_enabled() || !valid_email($to)) {
        return false;
    }
    $fromEmail = setting('mail_from_email');
    $fromName = setting('mail_from_name', store_name());
    $subject = str_replace(["\r", "\n"], '', $subject);
    $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/tr>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $boundary = 'b' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$boundary--";
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . (setting('store_email') ?: $fromEmail),
        'X-Mailer: UrbanCollections',
    ];
    try {
        if (setting('mail_driver', 'smtp') === 'smtp' && setting('smtp_host')) {
            return smtp_send($to, $subject, $body, $headers);
        }
        return mail($to, mb_encode_mimeheader($subject), $body, implode("\r\n", $headers), '-f' . $fromEmail);
    } catch (Throwable $e) {
        log_message('mail', 'Failed to send "' . $subject . '" to ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $subject, string $body, array $headers): bool
{
    $host = setting('smtp_host');
    $port = (int) setting('smtp_port', 465);
    $secure = setting('smtp_secure', 'ssl'); // ssl | tls | none
    $user = setting('smtp_user');
    $pass = decrypt_secret(setting('smtp_pass_enc', '')) ?? '';
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $errstr");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP error after "' . strtok($c, ' ') . '": ' . trim($r));
        }
        return $r;
    };
    $read();
    $ehloHost = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode($pass), [235]);
    }
    $from = setting('mail_from_email');
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $msg = 'To: <' . $to . ">\r\nSubject: " . mb_encode_mimeheader($subject) . "\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(10)) . '@' . $ehloHost . ">\r\n"
        . implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', $body);
    $cmd($msg . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

/** Wraps content in the branded email layout. */
function mail_layout(string $title, string $content): string
{
    $logo = url('assets/images/brand/logo-round.webp');
    return '<!doctype html><html><body style="margin:0;background:#F7F6F2;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F6F2;padding:32px 12px"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff">'
        . '<tr><td style="background:#111111;padding:28px;text-align:center"><img src="' . e($logo) . '" width="72" height="72" alt="' . e(store_name()) . '" style="border-radius:50%"><div style="color:#D8CBB8;letter-spacing:4px;font-size:13px;margin-top:10px">' . e(strtoupper(store_name())) . '</div></td></tr>'
        . '<tr><td style="padding:32px"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:24px;margin:0 0 16px">' . e($title) . '</h1>' . $content . '</td></tr>'
        . '<tr><td style="background:#E9E1D5;padding:20px;text-align:center;font-size:12px;color:#3A3A3A">' . e(store_name()) . ' · ' . e(setting('store_phone')) . ' · <a href="' . e(url()) . '" style="color:#3A3A3A">' . e(parse_url(SITE_URL, PHP_URL_HOST)) . '</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_order_table(array $order): string
{
    $rows = '';
    foreach (order_items((int) $order['id']) as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . e($it['product_name']) . ($it['variant_label'] ? '<br><small style="color:#777">' . e($it['variant_label']) . '</small>' : '')
            . ' × ' . (int) $it['qty'] . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right">' . e(money($it['line_total'])) . '</td></tr>';
    }
    $line = fn($l, $v) => '<tr><td style="padding:4px 0;color:#555">' . $l . '</td><td style="padding:4px 0;text-align:right">' . $v . '</td></tr>';
    $rows .= $line('Subtotal', e(money($order['subtotal'] + $order['customization_total'])));
    if ((float) $order['discount_total'] > 0) {
        $rows .= $line('Discount', '−' . e(money($order['discount_total'])));
    }
    $rows .= $line('Delivery', (float) $order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Free');
    if ((float) $order['payment_fee'] > 0) {
        $rows .= $line('Payment fee', e(money($order['payment_fee'])));
    }
    $rows .= '<tr><td style="padding:8px 0;font-weight:bold">Total</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . e(money($order['grand_total'])) . '</td></tr>';
    return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">' . $rows . '</table>';
}

function mail_order_confirmation(array $order, bool $paymentReceived = false): void
{
    $pm = payment_method_get($order['payment_method']);
    $link = url('order/' . $order['order_number'] . '?t=' . $order['access_token']);
    $intro = $paymentReceived
        ? '<p>We have received your payment for order <strong>' . e($order['order_number']) . '</strong>. Thank you!</p>'
        : '<p>Thank you for shopping with ' . e(store_name()) . '. Your order <strong>' . e($order['order_number']) . '</strong> has been received.</p>';
    $html = mail_layout($paymentReceived ? 'Payment received' : 'Thank you for your order', $intro
        . '<p style="color:#555">Payment: ' . e($pm['name'] ?? $order['payment_method']) . '<br>Estimated delivery: ' . e($order['estimated_delivery']) . '</p>'
        . mail_order_table($order)
        . '<p style="margin-top:24px"><a href="' . e($link) . '" style="background:#111;color:#fff;padding:12px 22px;text-decoration:none;display:inline-block">View / track your order</a></p>');
    send_mail($order['email'], ($paymentReceived ? 'Payment received — ' : 'Order confirmed — ') . $order['order_number'], $html);

    if (!$paymentReceived && setting('order_notify_email')) {
        $admin = mail_layout('New order ' . $order['order_number'], '<p>' . e($order['customer_name']) . ' (' . e($order['phone']) . ') placed an order.</p>'
            . mail_order_table($order) . '<p><a href="' . e(url('admin/orders/view.php?id=' . $order['id'])) . '">Open in admin</a></p>');
        foreach (array_filter(array_map('trim', explode(',', setting('order_notify_email')))) as $to) {
            send_mail($to, 'New order ' . $order['order_number'] . ' — ' . money($order['grand_total']), $admin);
        }
    }
}

function mail_order_status(array $order, string $note = ''): void
{
    $labels = order_statuses();
    $link = url('order/' . $order['order_number'] . '?t=' . $order['access_token']);
    $tracking = '';
    if ($order['tracking_number']) {
        $tracking = '<p>Courier: ' . e($order['courier_name']) . '<br>Tracking number: <strong>' . e($order['tracking_number']) . '</strong>'
            . ($order['tracking_url'] ? '<br><a href="' . e($order['tracking_url']) . '">Track shipment</a>' : '') . '</p>';
    }
    $html = mail_layout('Your order is ' . strtolower($labels[$order['status']] ?? $order['status']), '<p>Order <strong>' . e($order['order_number']) . '</strong> is now <strong>'
        . e($labels[$order['status']] ?? $order['status']) . '</strong>.</p>' . ($note ? '<p>' . nl2br(e($note)) . '</p>' : '') . $tracking
        . '<p><a href="' . e($link) . '" style="background:#111;color:#fff;padding:12px 22px;text-decoration:none;display:inline-block">View your order</a></p>');
    send_mail($order['email'], 'Order ' . $order['order_number'] . ' — ' . ($labels[$order['status']] ?? $order['status']), $html);
}
