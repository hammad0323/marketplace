<?php
/**
 * Input validation. Each helper returns the cleaned value or calls fail().
 * Usage:  $name = v_str($in, 'screen_name', required: true, max: 150);
 */

function v_error(string $field, string $msg): never
{
    fail($msg, 400, 'VALIDATION', [$field => $msg]);
}

function v_present(array $in, string $key): bool
{
    return array_key_exists($key, $in) && $in[$key] !== null && $in[$key] !== '';
}

function v_str(array $in, string $key, bool $required = false, int $max = 255, int $min = 0, ?string $default = null): ?string
{
    if (!v_present($in, $key)) {
        if ($required) v_error($key, ucfirst(str_replace('_', ' ', $key)) . ' is required');
        return $default;
    }
    if (!is_scalar($in[$key])) v_error($key, "{$key} must be text");
    $v = trim((string)$in[$key]);
    if ($required && $v === '') v_error($key, ucfirst(str_replace('_', ' ', $key)) . ' is required');
    if (mb_strlen($v) > $max) v_error($key, "{$key} must be at most {$max} characters");
    if (mb_strlen($v) < $min) v_error($key, "{$key} must be at least {$min} characters");
    return $v;
}

function v_int(array $in, string $key, bool $required = false, ?int $min = null, ?int $max = null, ?int $default = null): ?int
{
    if (!v_present($in, $key)) {
        if ($required) v_error($key, "{$key} is required");
        return $default;
    }
    if (filter_var($in[$key], FILTER_VALIDATE_INT) === false) v_error($key, "{$key} must be an integer");
    $v = (int)$in[$key];
    if ($min !== null && $v < $min) v_error($key, "{$key} must be at least {$min}");
    if ($max !== null && $v > $max) v_error($key, "{$key} must be at most {$max}");
    return $v;
}

function v_num(array $in, string $key, bool $required = false, ?float $min = null, ?float $max = null, ?float $default = null): ?float
{
    if (!v_present($in, $key)) {
        if ($required) v_error($key, "{$key} is required");
        return $default;
    }
    if (!is_numeric($in[$key])) v_error($key, "{$key} must be a number");
    $v = (float)$in[$key];
    if ($min !== null && $v < $min) v_error($key, "{$key} must be at least {$min}");
    if ($max !== null && $v > $max) v_error($key, "{$key} must be at most {$max}");
    return $v;
}

function v_bool(array $in, string $key, ?bool $default = null): ?bool
{
    if (!array_key_exists($key, $in) || $in[$key] === null || $in[$key] === '') return $default;
    $v = filter_var($in[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($v === null) v_error($key, "{$key} must be true or false");
    return $v;
}

function v_enum(array $in, string $key, array $allowed, bool $required = false, ?string $default = null): ?string
{
    $v = v_str($in, $key, $required, 100);
    if ($v === null) return $default;
    if (!in_array($v, $allowed, true)) v_error($key, "{$key} must be one of: " . implode(', ', $allowed));
    return $v;
}

function v_email(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 190);
    if ($v === null) return null;
    $v = strtolower($v);
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) v_error($key, 'Enter a valid email address');
    return $v;
}

function v_password(array $in, string $key, bool $required = true): ?string
{
    $v = $in[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) v_error($key, 'Password is required');
        return null;
    }
    $v = (string)$v;
    if (strlen($v) < 8) v_error($key, 'Password must be at least 8 characters');
    if (strlen($v) > 128) v_error($key, 'Password is too long');
    if (!preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) v_error($key, 'Password must contain letters and numbers');
    return $v;
}

function v_date(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 10);
    if ($v === null) return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) v_error($key, "{$key} must be a date (YYYY-MM-DD)");
    return $v;
}

function v_datetime(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 30);
    if ($v === null) return null;
    $ts = strtotime($v);
    if ($ts === false) v_error($key, "{$key} must be a date-time");
    return gmdate('Y-m-d H:i:s', $ts);
}

function v_time(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 8);
    if ($v === null) return null;
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $v)) v_error($key, "{$key} must be a time (HH:MM)");
    return strlen($v) === 5 ? $v . ':00' : $v;
}

function v_color(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 9);
    if ($v === null) return null;
    if (!preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) v_error($key, "{$key} must be a hex colour like #008C86");
    return strtoupper($v);
}

function v_timezone(array $in, string $key, bool $required = false): ?string
{
    $v = v_str($in, $key, $required, 64);
    if ($v === null) return null;
    if (!in_array($v, DateTimeZone::listIdentifiers(), true) && $v !== 'UTC') v_error($key, "{$key} is not a valid time zone");
    return $v;
}

function v_array(array $in, string $key, bool $required = false, int $maxItems = 1000): ?array
{
    if (!array_key_exists($key, $in) || $in[$key] === null) {
        if ($required) v_error($key, "{$key} is required");
        return null;
    }
    $v = $in[$key];
    if (is_string($v)) $v = json_decode($v, true);
    if (!is_array($v)) v_error($key, "{$key} must be an array");
    if (count($v) > $maxItems) v_error($key, "{$key} has too many items");
    return $v;
}

function v_int_list(array $in, string $key, bool $required = false): array
{
    $arr = v_array($in, $key, $required) ?? [];
    $out = [];
    foreach ($arr as $x) {
        if (filter_var($x, FILTER_VALIDATE_INT) === false || (int)$x < 1) v_error($key, "{$key} must contain positive integers");
        $out[] = (int)$x;
    }
    return array_values(array_unique($out));
}
