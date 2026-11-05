<?php
/**
 * Fixed-window rate limiter backed by the rate_limits table.
 *   rate_limit('login:ip:' . client_ip(), 10, 900);
 */
function rate_limit(string $bucket, int $max, int $windowSeconds): void
{
    $windowStart = gmdate('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);
    db_run('INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
        [mb_substr($bucket, 0, 190), $windowStart]);
    $hits = (int)db_val('SELECT hits FROM rate_limits WHERE bucket_key = ? AND window_start = ?', [mb_substr($bucket, 0, 190), $windowStart]);
    if ($hits > $max) {
        $retry = intdiv(time(), $windowSeconds) * $windowSeconds + $windowSeconds - time();
        header('Retry-After: ' . $retry);
        fail('Too many attempts. Please wait ' . max(1, (int)ceil($retry / 60)) . ' minute(s) and try again.', 429, 'RATE_LIMITED');
    }
}

function rate_limit_reset(string $bucket): void
{
    db_run('DELETE FROM rate_limits WHERE bucket_key = ?', [mb_substr($bucket, 0, 190)]);
}
