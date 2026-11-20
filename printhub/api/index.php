<?php
/**
 * PrintHub REST API v1 — used by the Flutter app and the web apps.
 * Every response: {"status": bool, "message": string, "data": object}
 * Auth:
 *   Shop owners  Authorization: Bearer <token>   (from POST /api/v1/auth/login)  — or the owner web cookie
 *   Customers    X-Customer-Token: <token>       (from POST /api/v1/customer/session) — or the "ph_cs" cookie on the website
 * Requests authenticated by cookie must send X-Requested-With: XMLHttpRequest from the same origin (CSRF protection).
 * See docs/API.md.
 */
if (!defined('PH_ROOT')) {
    require dirname(__DIR__) . '/core/bootstrap.php';
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function api_out(bool $ok, string $message, $data = null, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['status' => $ok, 'message' => $message, 'data' => $data ?? (object) []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_ok($data = null, string $message = 'OK', int $code = 200): never
{
    api_out(true, $message, $data, $code);
}

function api_fail(string $message, int $code = 422, array $errors = []): never
{
    api_out(false, $message, $errors ? ['errors' => $errors] : null, $code);
}

/** Cookie-authenticated writes must come from our own pages (custom header + same origin). */
function api_csrf_guard(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true) || bearer_token() || preg_match('/^[a-f0-9]{64}$/', $_SERVER['HTTP_X_CUSTOMER_TOKEN'] ?? '')) {
        return;
    }
    if (empty($_COOKIE['ph_cs']) && empty($_COOKIE['ph_owner'])) {
        return; // no ambient credentials: nothing a forged cross-site request could abuse
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $sameOrigin = $origin === '' || parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '') === $host;
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest' || !$sameOrigin) {
        api_fail('Request blocked. Please refresh the page and try again.', 403);
    }
}

function api_owner(): array
{
    $o = current_owner();
    if (!$o) {
        api_fail('Please sign in again.', 401);
    }
    return $o;
}

/** Owner actions that change data require an active (or grace-period) subscription. */
function api_owner_write(): array
{
    $o = api_owner();
    if (!subscription_allows_work((int) $o['shop']['id'], $o['shop'])) {
        api_fail('Your subscription has expired or is suspended. Please renew it to continue.', 402);
    }
    return $o;
}

function api_customer(): array
{
    $platform = ($_SERVER['HTTP_X_CLIENT'] ?? '') === 'app' ? 'app' : 'web';
    return customer_session(true, $platform);
}

function api_shop(string $code): array
{
    $shop = shop_by_code($code);
    if (!$shop || $shop['status'] === 'deactivated') {
        api_fail('Shop not found. Please scan the QR code again.', 404);
    }
    return $shop;
}

function api_rate(string $key, int $max, int $window): void
{
    if (!rate_limit($key, $max, $window)) {
        api_fail('Too many requests. Please wait a moment and try again.', 429);
    }
}

// ---------------------------------------------------------------- CORS (extra origins only; bearer tokens, no cookies)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, CORS_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Customer-Token, X-Client, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------- routing
$uri = '/' . trim((string) preg_replace('#^' . preg_quote(app_base_path(), '#') . '#', '', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
if (!preg_match('#^/api/v1(/.*)?$#', $uri, $m)) {
    api_fail('Unknown API version. Use /api/v1/.', 404);
}
$route = $m[1] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] === 'PUT' ? 'POST' : $_SERVER['REQUEST_METHOD'];

api_rate('api:' . client_ip(), 600, 300);
api_csrf_guard();

foreach (['auth', 'owner', 'public', 'customer', 'cv'] as $mod) {
    require __DIR__ . '/modules/' . $mod . '.php';
}

$routes = array_merge(api_routes_auth(), api_routes_owner(), api_routes_public(), api_routes_customer(), api_routes_cv());
foreach ($routes as [$verb, $pattern, $handler]) {
    if ($verb !== $method && !($verb === 'POST' && $method === 'PATCH')) {
        continue;
    }
    if (preg_match('#^' . $pattern . '$#', $route, $args)) {
        array_shift($args);
        $handler(...$args);
        api_fail('No response.', 500);
    }
}
api_fail('Endpoint not found.', 404);
