<?php
/**
 * Small stateless helper functions used across page scripts.
 */

function config_get(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);

    return $text !== '' ? $text : 'n-a';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function old(string $key, $default = '')
{
    return $_SESSION['_old_input'][$key] ?? $default;
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
}

function marketplace_badge(string $marketplaceType): string
{
    $types = config_get('marketplace_types', []);
    return $types[$marketplaceType]['badge'] ?? $marketplaceType;
}

function render_product_card(array $product): void
{
    require __DIR__ . '/../partials/product-card.php';
}
