<?php
/** Authentication APIs (shop owners). No public registration: accounts are created by the Super Admin. */

function owner_payload(array $user, array $shop): array
{
    $st = subscription_state((int) $shop['id'], $shop);
    return [
        'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'username' => $user['username'], 'email' => $user['email'], 'phone' => $user['phone'],
            'role' => $user['role'], 'must_change_password' => (bool) $user['must_change_password']],
        'shop' => owner_shop_payload($shop),
        'subscription' => ['state' => $st['state'], 'label' => $st['label'], 'end_date' => $st['end_date'], 'grace_until' => $st['grace_until'],
            'days_left' => $st['days_left'], 'price' => $st['price'], 'can_work' => in_array($st['state'], ['active', 'grace'], true)],
    ];
}

function owner_shop_payload(array $shop): array
{
    return ['id' => (int) $shop['id'], 'shop_code' => $shop['shop_code'], 'name' => $shop['name'], 'owner_name' => $shop['owner_name'], 'phone' => $shop['phone'],
        'whatsapp' => $shop['whatsapp'], 'email' => $shop['email'], 'address' => $shop['address'], 'area' => $shop['area'], 'city' => $shop['city'],
        'latitude' => $shop['latitude'] !== null ? (float) $shop['latitude'] : null, 'longitude' => $shop['longitude'] !== null ? (float) $shop['longitude'] : null,
        'logo_url' => $shop['logo_path'] ? url('media/' . $shop['logo_path']) : null, 'status' => $shop['status'], 'accepting_orders' => (bool) $shop['accepting_orders'],
        'customer_url' => shop_url($shop)];
}

function api_routes_auth(): array
{
    return [
        ['POST', '/auth/login', function () {
            api_rate('login:' . client_ip(), 20, 900);
            $r = owner_login(in_str('username', 190), (string) in('password', ''));
            if (is_string($r)) {
                api_fail($r, 401);
            }
            [$user, $shop] = $r;
            $token = token_issue('owner', (int) $user['id'], (int) $shop['id'], in_str('device_name', 120) ?: 'App');
            api_ok(['token' => $token, 'expires_in_days' => TOKEN_TTL_DAYS] + owner_payload($user, $shop), 'Signed in');
        }],
        ['POST', '/auth/logout', function () {
            $o = api_owner();
            token_revoke($o['token']);
            api_ok(null, 'Signed out');
        }],
        ['GET', '/auth/me', function () {
            $o = api_owner();
            $u = db_row('SELECT * FROM shop_users WHERE id = ?', [(int) $o['user']['id']]);
            api_ok(owner_payload($u, $o['shop']));
        }],
        ['POST', '/auth/forgot', function () {
            api_rate('forgot:' . client_ip(), 5, 3600);
            owner_reset_request(in_str('login', 190));
            api_ok(null, 'If the account has an email address, a reset link has been sent. Otherwise contact the platform admin to reset your password.');
        }],
        ['POST', '/auth/reset', function () {
            api_rate('reset:' . client_ip(), 10, 3600);
            $err = owner_reset_apply(in_str('token', 100), (string) in('password', ''));
            $err ? api_fail($err) : api_ok(null, 'Password updated. Please sign in.');
        }],
        ['POST', '/auth/change-password', function () {
            $o = api_owner();
            $err = owner_change_password($o['user'], (string) in('current_password', ''), (string) in('new_password', ''), $o['token']);
            $err ? api_fail($err) : api_ok(null, 'Password changed. Other devices were signed out.');
        }],
        ['POST', '/auth/profile', function () {
            $o = api_owner();
            $name = in_str('name', 120);
            $email = in_str('email', 190);
            $phone = clean_phone(in_str('phone', 30));
            if (mb_strlen($name) < 2) api_fail('Enter your name.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) api_fail('Enter a valid email address.');
            db_update('shop_users', ['name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null], 'id = ?', [(int) $o['user']['id']]);
            $u = db_row('SELECT * FROM shop_users WHERE id = ?', [(int) $o['user']['id']]);
            api_ok(owner_payload($u, $o['shop']), 'Profile saved');
        }],
    ];
}
