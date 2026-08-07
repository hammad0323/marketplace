<?php
/**
 * Tenant resolution — the one place a request's Host header is turned
 * into "which signed-up business's marketplace is this." Required as
 * the LAST line of config/config.php (after every helper/middleware/
 * function file is already autoloaded), so it can freely call
 * mp_find_tenant_by_subdomain(), mp_set_current_tenant(), mp_e(), etc.
 *
 * Three kinds of host, in this order:
 *   1. The bare base domain (or platform.{base}) — no tenant; bare
 *      domain sends visitors to signup, platform.{base} is the
 *      SaaS-operator control panel.
 *   2. {subdomain}.{base} — resolve the tenant; block with 404/503
 *      if it doesn't exist or is suspended.
 *   3. Requests under /assets/, /signup/, or /platform/ always pass
 *      through untouched regardless of host, since none of those are
 *      tenant-scoped.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isExemptPath = str_starts_with($requestUri, ROUTE_ASSETS)
    || str_starts_with($requestUri, ROUTE_SIGNUP)
    || str_starts_with($requestUri, ROUTE_PLATFORM);

if ($isExemptPath) {
    mp_set_current_tenant(null);
    return;
}

$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '', 2)[0]);
$baseDomain = strtolower(APP_BASE_DOMAIN);

if ($host === $baseDomain || $host === 'www.' . $baseDomain) {
    mp_set_current_tenant(null);
    mp_redirect(ROUTE_SIGNUP . 'start.php');
}

if ($host === 'platform.' . $baseDomain) {
    mp_set_current_tenant(null);
    return;
}

$suffix = '.' . $baseDomain;
if (!str_ends_with($host, $suffix)) {
    http_response_code(404);
    exit('Unknown host.');
}

$subdomain = substr($host, 0, -strlen($suffix));
$tenant = mp_find_tenant_by_subdomain($subdomain);

if (!$tenant) {
    http_response_code(404);
    exit('No such marketplace.');
}

if ($tenant['status'] === 'suspended') {
    http_response_code(503);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= mp_e($tenant['business_name']) ?> — Unavailable</title>
        <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/global.css">
    </head>
    <body class="theme-main">
        <main class="site-main" style="text-align:center; padding: 6rem 1rem;">
            <span class="empty-state-icon">⏸️</span>
            <h1><?= mp_e($tenant['business_name']) ?> is temporarily unavailable</h1>
            <p>This marketplace has been suspended. If you're the owner, contact the platform operator.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

mp_set_current_tenant($tenant);
