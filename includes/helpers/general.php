<?php
/**
 * Generic helpers used across every module: output escaping, slugs,
 * redirects, flash messages, CSRF, and the small shared UI bits. No
 * classes — just functions, all prefixed mp_ to avoid collisions.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function mp_slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);

    return $text !== '' ? $text : 'n-a';
}

function mp_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function mp_old(string $key, $default = '')
{
    return $_SESSION['_old_input'][$key] ?? $default;
}

function mp_flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function mp_csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function mp_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . mp_e(mp_csrf_token()) . '">';
}

function mp_verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
}

/**
 * Formats a price using the current tenant's admin-configurable
 * currency symbol (settings table), e.g. mp_currency(48) => "$48.00".
 * Falls back to a plain "$" outside any tenant context (platform/
 * pages showing cross-tenant totals) — there's no single tenant's
 * currency to use there, so a neutral fallback is the correct
 * behavior, not just a technical workaround.
 */
function mp_currency(float $amount): string
{
    $symbol = mp_current_tenant() ? mp_get_setting('currency_symbol', '$') : '$';
    return $symbol . number_format($amount, 2);
}

/** Badge shown next to products/stores so customers know which marketplace they're in — reads this tenant's own marketplace_types row instead of a hardcoded label. */
function mp_marketplace_badge(string $marketplaceType): string
{
    $type = mp_find_marketplace_type_by_slug($marketplaceType);
    return $type['badge_label'] ?? $marketplaceType;
}

function mp_render_product_card(array $product): void
{
    require __DIR__ . '/../../products/product-card.php';
}
