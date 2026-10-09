<?php
/**
 * Admin bootstrap — every admin page starts with:
 *   require __DIR__ . '/_inc/bootstrap.php';
 *   $admin = require_admin('permission.key');
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require __DIR__ . '/form.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

/** Sidebar definition: [label, icon, url, permission, children?] */
function admin_menu(): array
{
    return [
        ['Dashboard', 'speedometer2', 'index', 'dashboard.view'],
        ['Orders', 'bag-check', 'orders', 'orders.view'],
        ['Catalogue', 'box-seam', null, null, [
            ['Products', 'products', 'products.view'],
            ['Categories', 'categories', 'categories.manage'],
            ['Collections', 'collections', 'collections.manage'],
            ['Inventory', 'inventory', 'inventory.manage'],
            ['Reviews', 'reviews', 'reviews.manage'],
        ]],
        ['Customers', 'people', null, null, [
            ['Customers', 'customers', 'customers.view'],
            ['Newsletter', 'newsletter', 'newsletter.manage'],
            ['Messages', 'messages', 'messages.view'],
        ]],
        ['Marketing', 'megaphone', null, null, [
            ['Coupons', 'coupons', 'coupons.manage'],
        ]],
        ['Storefront', 'window', null, null, [
            ['Homepage builder', 'homepage', 'content.homepage'],
            ['Hero slides', 'slides', 'content.slides'],
            ['Testimonials', 'testimonials', 'content.testimonials'],
            ['Pages', 'pages', 'content.pages'],
            ['Theme', 'theme', 'settings.theme'],
        ]],
        ['Settings', 'gear', null, null, [
            ['General', 'settings', 'settings.general'],
            ['Shipping & delivery', 'shipping', 'shipping.manage'],
            ['Payments', 'payments', 'payments.manage'],
            ['Transactions', 'transactions', 'payments.manage'],
            ['SEO', 'seo', 'seo.manage'],
            ['Redirects', 'redirects', 'seo.manage'],
        ]],
        ['System', 'shield-lock', null, null, [
            ['Administrators', 'admins', 'admins.manage'],
            ['Roles & permissions', 'roles', 'admins.manage'],
            ['Audit log', 'audit-log', 'audit.view'],
        ]],
    ];
}

function admin_header(string $title, string $active = ''): void
{
    $GLOBALS['admin_title'] = $title;
    $GLOBALS['admin_active'] = $active;
    require __DIR__ . '/header.php';
}

function admin_footer(): void
{
    require __DIR__ . '/footer.php';
}

/** POST guard: CSRF + permission. */
function admin_post(?string $perm = null): void
{
    if (!is_post()) {
        http_response_code(405);
        exit('Method not allowed');
    }
    require_csrf();
    if ($perm) {
        require_admin($perm);
    }
}

function admin_back(string $fallback): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref && strpos($ref, url('admin')) === 0 ? $ref : admin_url($fallback));
}

/** Sanitised product search for pickers (id => label). */
function product_options(): array
{
    $out = [];
    foreach (db_all('SELECT id, name, sku, status FROM products ORDER BY name') as $p) {
        $out[$p['id']] = $p['name'] . ' (' . $p['sku'] . ')' . ($p['status'] !== 'published' ? ' — ' . $p['status'] : '');
    }
    return $out;
}

function category_options(bool $withEmpty = true, ?int $excludeId = null): array
{
    $out = $withEmpty ? ['' => '— None —'] : [];
    $walk = function (array $nodes, int $depth) use (&$walk, &$out, $excludeId) {
        foreach ($nodes as $n) {
            if ($excludeId && (int) $n['id'] === $excludeId) {
                continue;
            }
            $out[$n['id']] = str_repeat('— ', $depth) . $n['name'] . ((int) $n['is_active'] ? '' : ' (disabled)');
            $walk($n['children'], $depth + 1);
        }
    };
    $walk(category_tree(false), 0);
    return $out;
}

/** CSV download helper (escapes formula injection). */
function csv_download(string $filename, array $header, iterable $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, array_map(function ($v) {
            $v = (string) $v;
            return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        }, $r));
    }
    fclose($out);
    exit;
}
