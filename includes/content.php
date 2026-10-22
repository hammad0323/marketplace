<?php
/**
 * Content pages and newsletter subscriptions.
 */

function page_by_slug(string $slug, bool $includeDrafts = false): ?array
{
    return db_row('SELECT * FROM pages WHERE slug = ?' . ($includeDrafts ? '' : " AND status = 'published'"), [$slug]);
}

/**
 * Subscribes an email (idempotent). Returns 'subscribed', 'already' or 'resubscribed'.
 */
function newsletter_subscribe(string $email, string $source, string $consentText): string
{
    $email = strtolower(trim($email));
    $row = db_row('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
    if ($row && $row['status'] === 'subscribed') {
        return 'already';
    }
    if ($row) {
        db_exec("UPDATE newsletter_subscribers SET status = 'subscribed', consent_text = ?, consent_at = NOW(), consent_ip = ?, unsubscribed_at = NULL, source = ? WHERE id = ?",
            [mb_substr($consentText, 0, 255), client_ip(), $source, (int) $row['id']]);
        return 'resubscribed';
    }
    $token = random_token(20);
    db_insert('newsletter_subscribers', [
        'email' => $email, 'status' => 'subscribed', 'consent_text' => mb_substr($consentText, 0, 255), 'consent_at' => date('Y-m-d H:i:s'),
        'consent_ip' => client_ip(), 'unsubscribe_token' => $token, 'source' => $source,
    ]);
    send_mail($email, 'Welcome to ' . store_name(), mail_layout('Welcome to the inner circle', '<p>Thank you for subscribing. You will be first to hear about new arrivals, private sales and style notes.</p>'
        . '<p style="font-size:12px;color:#777">Changed your mind? <a href="' . e(url('newsletter/unsubscribe?token=' . $token)) . '">Unsubscribe</a> at any time.</p>'));
    return 'subscribed';
}
