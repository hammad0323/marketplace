<?php
/**
 * Sessions, CSRF, rate limiting, security headers and secret encryption.
 */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('beglet_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (base_path() ?: '') . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();

    // Absolute idle timeout for admin sessions (2h) — customers stay logged in for the browser session.
    if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_last_seen']) && time() - $_SESSION['admin_last_seen'] > 7200) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    }
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function token_hash(string $token): string
{
    return hash_hmac('sha256', $token, APP_KEY);
}

// ---- CSRF -------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = random_token(32);
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Abort a POST request with an invalid CSRF token. */
function require_csrf(): void
{
    if (!csrf_valid()) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 419);
        }
        http_response_code(419);
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? path_url('/'));
    }
}

// ---- Rate limiting ----------------------------------------------------------

/**
 * Returns true when the action is allowed, recording the attempt.
 * $max attempts per $windowSeconds for (action, identity).
 */
function rate_limit(string $action, string $identity, int $max, int $windowSeconds): bool
{
    $hash = hash('sha256', $action . '|' . $identity);
    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    $count = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE action = ? AND ident_hash = ? AND created_at > ?', [$action, $hash, $since]);
    if ($count >= $max) {
        return false;
    }
    db_insert('rate_limits', ['action' => $action, 'ident_hash' => $hash]);
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
    return true;
}

function rate_limit_clear(string $action, string $identity): void
{
    db_exec('DELETE FROM rate_limits WHERE action = ? AND ident_hash = ?', [$action, hash('sha256', $action . '|' . $identity)]);
}

// ---- Secret encryption (payment credentials at rest) -------------------------

function app_key_bytes(): string
{
    $hex = preg_replace('/[^0-9a-f]/i', '', APP_KEY);
    if (strlen($hex) >= 64) {
        return hex2bin(substr($hex, 0, 64));
    }
    return hash('sha256', APP_KEY, true);
}

function app_key_is_default(): bool
{
    return strpos(APP_KEY, 'REPLACE_WITH') === 0 || strlen(preg_replace('/[^0-9a-f]/i', '', APP_KEY)) < 64;
}

function encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'enc:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, app_key_bytes()));
}

function decrypt_secret(?string $stored): string
{
    $stored = (string) $stored;
    if (strpos($stored, 'enc:') !== 0) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return '';
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, app_key_bytes());
    return $plain === false ? '' : $plain;
}

/**
 * Minimal HTML sanitiser for admin-authored rich content (pages, product
 * descriptions). Keeps a whitelist of formatting tags and strips every
 * attribute except safe href/src/alt/title.
 */
function sanitize_html(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'span', 'small'];
    $doc = new DOMDocument();
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
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                $keep = ['a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height']][$tag] ?? [];
                for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                    $attr = $child->attributes->item($a);
                    $name = strtolower($attr->nodeName);
                    if (!in_array($name, $keep, true)) {
                        $child->removeAttribute($attr->nodeName);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && !preg_match('#^(https?://|/|\#|mailto:|tel:|uploads/|assets/)#i', trim($attr->nodeValue))) {
                        $child->removeAttribute($attr->nodeName);
                    }
                }
                if ($tag === 'a' && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                    $child->setAttribute('rel', 'noopener');
                }
                if ($tag === 'img') {
                    $child->setAttribute('loading', 'lazy');
                }
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

/** Render plain text with paragraphs; HTML if it already contains tags (sanitised on save). */
function rich_text(?string $content): string
{
    $content = (string) $content;
    if ($content !== strip_tags($content)) {
        return $content;
    }
    $paras = preg_split('/\n\s*\n/', trim($content));
    return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', array_filter($paras, 'strlen')));
}
