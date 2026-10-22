<?php
/**
 * Sessions, CSRF, output escaping, rate limiting, HTML sanitising and secret encryption.
 */

function uc_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function uc_start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli' || defined('UC_NO_SESSION')) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_name('uc_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_PATH ?: '') . '/',
        'secure'   => uc_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Bind the session to the user agent to make stolen session ids less useful.
    $ua = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 16);
    if (!isset($_SESSION['_ua'])) {
        $_SESSION['_ua'] = $ua;
    } elseif ($_SESSION['_ua'] !== $ua) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_ua'] = $ua;
    }
}

/** Call after any privilege change (login, logout, password change). */
function uc_regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    unset($_SESSION['_csrf']);
}

function uc_send_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (uc_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** HTML-escape for output. Use for every piece of data printed into a page. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for a JS string inside HTML (json-encoded). */
function ejs($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

// ---------------------------------------------------------------------
//  CSRF
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
}

/** Aborts the request when the CSRF token is missing or wrong. */
function csrf_check(): void
{
    if (!csrf_valid()) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'], 419);
        }
        http_response_code(419);
        exit('<div style="font-family:sans-serif;max-width:560px;margin:80px auto">'
            . '<h2>Session expired</h2><p>Please go back, refresh the page and try again.</p></div>');
    }
}

// ---------------------------------------------------------------------
//  Rate limiting (fixed window, stored in MySQL so it works on shared hosting)
// ---------------------------------------------------------------------
/**
 * Returns true when the action is allowed. $key should identify the action and actor,
 * e.g. 'login:' . client_ip().
 */
function rate_limit(string $key, int $max, int $windowSeconds): bool
{
    $key = substr($key, 0, 120);
    $now = time();
    $row = db_row('SELECT hits, window_start FROM rate_limits WHERE rate_key = ?', [$key]);
    if (!$row || $now - (int) $row['window_start'] >= $windowSeconds) {
        db_exec('REPLACE INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?)', [$key, $now]);
        if (random_int(1, 50) === 1) {
            db_exec('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400]);
        }
        return true;
    }
    if ((int) $row['hits'] >= $max) {
        return false;
    }
    db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?', [$key]);
    return true;
}

function rate_limit_reset(string $key): void
{
    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [substr($key, 0, 120)]);
}

// ---------------------------------------------------------------------
//  HTML sanitiser for admin-authored rich text (pages, descriptions, story)
// ---------------------------------------------------------------------
function sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'blockquote' => [], 'hr' => [], 'small' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'span' => ['style'], 'div' => ['class'],
        'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'], 'figure' => [], 'figcaption' => [], 'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title'],
    ];
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) {
        return e(strip_tags($html));
    }
    $walk = function (DOMNode $node) use (&$walk, $allowed, $doc) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!isset($allowed[$tag])) {
                    if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    $walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $val = trim($attr->value);
                    if (!in_array($name, $allowed[$tag], true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true)) {
                        $ok = preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $val) && !preg_match('#^\s*(javascript|data|vbscript):#i', $val);
                        if ($tag === 'iframe') {
                            $ok = (bool) preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#i', $val);
                        }
                        if (!$ok) {
                            $child->removeAttribute($attr->name);
                        }
                    }
                    if ($name === 'style' && preg_match('/expression|url\s*\(|javascript/i', $val)) {
                        $child->removeAttribute('style');
                    }
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                $walk($child);
            } elseif ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return $out;
}

// ---------------------------------------------------------------------
//  Secret encryption (payment credentials) — AES-256-GCM with APP_KEY
// ---------------------------------------------------------------------
function uc_app_key(): string
{
    if (!defined('APP_KEY') || !preg_match('/^[a-f0-9]{64}$/i', APP_KEY)) {
        throw new RuntimeException('APP_KEY in config/config.php must be 64 hex characters.');
    }
    return hex2bin(APP_KEY);
}

function encrypt_secret(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', uc_app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $encoded): ?string
{
    if (!$encoded) {
        return null;
    }
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', uc_app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

function random_token(int $bytes = 20): string
{
    return bin2hex(random_bytes($bytes));
}
