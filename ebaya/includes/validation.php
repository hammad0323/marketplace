<?php
/** Shared input validation. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function v_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Pakistani and international numbers: digits, spaces, +, -, 7–20 chars. */
function v_phone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    return (bool)preg_match('/^\+?[0-9 \-()]{7,20}$/', $phone) && strlen($digits) >= 7 && strlen($digits) <= 15;
}

function v_len(string $s, int $min, int $max): bool
{
    $l = mb_strlen($s);
    return $l >= $min && $l <= $max;
}

function v_password(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (strlen($p) > 128) return 'Password is too long.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/[0-9]/', $p)) return 'Password must contain letters and numbers.';
    return null;
}

function v_slug(string $s): bool
{
    return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && strlen($s) <= 170;
}

function v_hex(string $c): bool
{
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

/** Accept relative paths (/shop), anchors and http(s) URLs only. */
function v_url(string $u): bool
{
    if ($u === '' || $u[0] === '/' || $u[0] === '#') return !preg_match('#^//#', $u) || (bool)filter_var('https:' . $u, FILTER_VALIDATE_URL);
    if (preg_match('#^(mailto|tel):#i', $u)) return true;
    return (bool)filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u);
}

function clean_url(string $u): string
{
    $u = trim($u);
    return v_url($u) ? $u : '';
}

function to_money($v): ?float
{
    if ($v === '' || $v === null) return null;
    $v = str_replace([',', ' '], '', (string)$v);
    return is_numeric($v) && $v >= 0 ? round((float)$v, 2) : null;
}

function to_int($v, int $default = 0): int
{
    return is_numeric($v) ? (int)$v : $default;
}

function in_list($v, array $allowed, $default)
{
    return in_array($v, $allowed, true) ? $v : $default;
}

function clamp_int($v, int $min, int $max): int
{
    return max($min, min($max, (int)$v));
}
