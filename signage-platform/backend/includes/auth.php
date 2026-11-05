<?php
/**
 * Token authentication for users (management apps) and devices (players).
 * Only SHA-256 hashes of tokens are stored.
 */

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $h = $v;
    }
    if (preg_match('/^Bearer\s+([A-Za-z0-9\-_.]{16,200})$/', trim($h), $m)) return $m[1];
    return null;
}

/** Issue a new API token for a user. Returns [token, expires_at]. */
function issue_user_token(int $userId, ?string $label = null): array
{
    $token = random_token(32);
    $exp   = gmdate('Y-m-d H:i:s', time() + max(1, (int)TOKEN_TTL_DAYS) * 86400);
    db_run('INSERT INTO api_tokens (user_id, token_hash, device_label, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, sha256($token), $label ? mb_substr($label, 0, 150) : null, client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $exp]);
    return [$token, $exp];
}

function user_account_problem(array $u): ?array
{
    if ($u['deleted_at'] !== null) return ['This account no longer exists', 'UNAUTHENTICATED'];
    if ($u['status'] === 'suspended') return ['Your account has been suspended. Please contact your administrator.', 'ACCOUNT_SUSPENDED'];
    if ($u['status'] === 'inactive') return ['Your account is inactive. Please contact your administrator.', 'ACCOUNT_INACTIVE'];
    if ($u['account_expires_at'] !== null && strtotime($u['account_expires_at'] . ' UTC') < time()) return ['Your account has expired. Please contact your administrator.', 'ACCOUNT_EXPIRED'];
    return null;
}

/** Authenticated user or 401. Result includes role_key and token_id. */
function require_user(): array
{
    static $user = null;
    if ($user !== null) return $user;
    $token = bearer_token();
    if (!$token) fail('Authentication required', 401, 'UNAUTHENTICATED');
    $row = db_one(
        'SELECT u.user_id, u.role_id, r.role_key, u.full_name, u.email, u.phone, u.profile_image, u.business_name, u.timezone,
                u.status, u.must_change_password, u.account_expires_at, u.last_login_at, u.created_at, u.deleted_at,
                t.token_id, t.expires_at AS token_expires_at, t.last_used_at
           FROM api_tokens t
           JOIN users u ON u.user_id = t.user_id
           JOIN roles r ON r.role_id = u.role_id
          WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP()',
        [sha256($token)]
    );
    if (!$row) fail('Your session has expired. Please sign in again.', 401, 'UNAUTHENTICATED');
    if ($p = user_account_problem($row)) fail($p[0], 403, $p[1]);
    if ($row['last_used_at'] === null || strtotime($row['last_used_at'] . ' UTC') < time() - 300) {
        db_run('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE token_id = ?', [$row['token_id']]);
    }
    if (app_setting('maintenance_mode', false) && $row['role_key'] !== ROLE_SUPER_ADMIN) {
        fail('The service is under maintenance. Please try again shortly.', 503, 'MAINTENANCE');
    }
    unset($row['deleted_at']);
    return $user = $row;
}

function is_admin(array $user): bool
{
    return $user['role_key'] === ROLE_SUPER_ADMIN;
}

function require_admin(): array
{
    $u = require_user();
    if (!is_admin($u)) fail('Administrator access required', 403, 'FORBIDDEN');
    return $u;
}

/** Authenticated device (player) or 401. */
function require_device(): array
{
    static $device = null;
    if ($device !== null) return $device;
    $token = bearer_token();
    if (!$token) fail('Device authentication required', 401, 'UNAUTHENTICATED');
    $row = db_one('SELECT * FROM devices WHERE device_token_hash = ? AND deleted_at IS NULL', [sha256($token)]);
    if (!$row) fail('Unknown device. Please register again.', 401, 'DEVICE_UNKNOWN');
    if ($row['status'] === 'disabled') fail('This display has been disabled', 403, 'DEVICE_DISABLED');
    return $device = $row;
}

/** Public user profile (never includes password hash). */
function present_user(array $u): array
{
    $out = [];
    foreach (['user_id', 'role_id', 'role_key', 'full_name', 'email', 'phone', 'business_name', 'timezone', 'status',
                 'must_change_password', 'account_expires_at', 'last_login_at', 'created_at'] as $k) {
        if (array_key_exists($k, $u)) $out[$k] = $u[$k];
    }
    $out['profile_image_url'] = !empty($u['profile_image']) ? base_url() . '/uploads/' . ltrim($u['profile_image'], '/') : null;
    if (isset($out['must_change_password'])) $out['must_change_password'] = (bool)$out['must_change_password'];
    return $out;
}
