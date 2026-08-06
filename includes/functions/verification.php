<?php
/**
 * Email verification — shared token logic for both vendors and
 * customers. A token is generated at registration and mailed via
 * mp_notify() (logged to logs/notifications.log until a real mailer
 * is wired up); visiting the verify link with a matching, unexpired
 * token sets email_verified_at. Neither role is blocked from using
 * the site while unverified — mp_require_vendor()/dashboards just
 * show a reminder banner until it's confirmed.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

const MP_VERIFICATION_TOKEN_TTL_HOURS = 48;

function mp_generate_verification_token(): string
{
    return bin2hex(random_bytes(24));
}

function mp_set_vendor_verification_token(int $vendorId): string
{
    $token = mp_generate_verification_token();
    mp_db_execute(
        'UPDATE vendors SET verification_token = ?, verification_token_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?',
        [$token, MP_VERIFICATION_TOKEN_TTL_HOURS, $vendorId]
    );
    return $token;
}

function mp_set_customer_verification_token(int $customerId): string
{
    $token = mp_generate_verification_token();
    mp_db_execute(
        'UPDATE customers SET verification_token = ?, verification_token_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?',
        [$token, MP_VERIFICATION_TOKEN_TTL_HOURS, $customerId]
    );
    return $token;
}

/** Verifies the token, marks the vendor's email verified, and returns the vendor row — or null if the token is missing/expired. */
function mp_verify_vendor_token(string $token): ?array
{
    $vendor = mp_db_fetch_one(
        'SELECT * FROM vendors WHERE verification_token = ? AND verification_token_expires_at > NOW() LIMIT 1',
        [$token]
    );
    if (!$vendor) {
        return null;
    }

    mp_db_execute(
        'UPDATE vendors SET email_verified_at = NOW(), verification_token = NULL, verification_token_expires_at = NULL WHERE id = ?',
        [$vendor['id']]
    );

    return $vendor;
}

function mp_verify_customer_token(string $token): ?array
{
    $customer = mp_db_fetch_one(
        'SELECT * FROM customers WHERE verification_token = ? AND verification_token_expires_at > NOW() LIMIT 1',
        [$token]
    );
    if (!$customer) {
        return null;
    }

    mp_db_execute(
        'UPDATE customers SET email_verified_at = NOW(), verification_token = NULL, verification_token_expires_at = NULL WHERE id = ?',
        [$customer['id']]
    );

    return $customer;
}
