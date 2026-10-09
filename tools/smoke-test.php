<?php
/**
 * End-to-end smoke test against a running STAGING copy of the store.
 * Creates a real COD order — never run against production.
 *
 *   php tools/smoke-test.php http://localhost:8080 admin@example.com 'AdminPassword123'
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
[$_, $base, $adminEmail, $adminPass] = $argv + [null, 'http://localhost:8080', 'admin@example.com', 'ChangeMe@2026'];
$base = rtrim($base, '/');
$fail = 0;
$jar = tempnam(sys_get_temp_dir(), 'beglet');

function req(string $method, string $url, array $data = [], array $headers = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hs);
    preg_match('/^Location:\s*(.+)$/mi', $head, $m);
    return ['code' => $code, 'body' => substr($raw, $hs), 'location' => trim($m[1] ?? '')];
}
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? "  \u{2713} " : "  \u{2717} ") . $label . ($ok ? '' : "  $extra") . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}
function csrf_from(string $html): string
{
    preg_match('/"csrf":"([a-f0-9]{64})"/', $html, $m) || preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $m) || preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $html, $m);
    return $m[1] ?? '';
}
function no_php_errors(string $html): bool
{
    return !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $html);
}
$ajax = ['X-Requested-With: XMLHttpRequest'];

echo "Storefront\n";
foreach (['/', '/shop', '/category/bifold-wallets', '/collection/the-noir-edit', '/search?q=wallet', '/about-us', '/contact', '/track-order', '/account/login', '/cart', '/sitemap.xml', '/robots.txt'] as $p) {
    $r = req('GET', $base . $p);
    check("GET $p → 200", $r['code'] === 200 && no_php_errors($r['body']), (string) $r['code']);
}
check('Unknown URL → 404', req('GET', $base . '/does-not-exist')['code'] === 404);
check('app/config.php is blocked', req('GET', $base . '/app/config.php')['code'] === 403);
check('install/*.sql is blocked', req('GET', $base . '/install/database.sql')['code'] === 403);

$pdp = req('GET', $base . '/product/heritage-bifold-wallet');
check('Product page renders with JSON-LD', $pdp['code'] === 200 && strpos($pdp['body'], '"@type":"Product"') !== false);
$csrf = csrf_from($pdp['body']);
preg_match('/data-variants="([^"]+)"/', $pdp['body'], $m);
$variants = json_decode(html_entity_decode($m[1] ?? '[]'), true);
$variant = null;
foreach ($variants as $v) {
    if ($v['available']) { $variant = $v; break; }
}
preg_match('/name="product_id" value="(\d+)"/', $pdp['body'], $pm);
$pid = (int) ($pm[1] ?? 0);

echo "Cart\n";
$r = req('POST', $base . '/api/cart', ['action' => 'add', 'product_id' => $pid, 'variant_id' => 0, 'quantity' => 1, 'csrf_token' => $csrf], $ajax);
check('Adding without choosing a variant is rejected', $r['code'] === 422);
$r = req('POST', $base . '/api/cart', ['action' => 'add', 'product_id' => $pid, 'variant_id' => $variant['id'], 'quantity' => 2, 'csrf_token' => $csrf], $ajax);
check('Add variant to cart', $r['code'] === 200 && (json_decode($r['body'], true)['count'] ?? 0) === 2, $r['body']);
$r = req('POST', $base . '/api/cart', ['action' => 'add', 'product_id' => $pid, 'variant_id' => $variant['id'], 'quantity' => 1], $ajax);
check('Cart API without CSRF token is refused (403)', $r['code'] === 403);
$r = req('POST', $base . '/api/cart', ['action' => 'add', 'product_id' => $pid, 'variant_id' => $variant['id'], 'quantity' => 9999, 'csrf_token' => $csrf], $ajax);
$cnt = json_decode($r['body'], true)['count'] ?? 999;
check('Huge quantity is capped by stock / per-line limit', $r['code'] === 422 || $cnt <= 20, $r['body']);
$r = req('POST', $base . '/api/cart', ['action' => 'coupon', 'code' => 'WELCOME10', 'csrf_token' => $csrf, 'view' => 'page'], $ajax);
check('Coupon WELCOME10 applies', $r['code'] === 200, $r['body']);

echo "Checkout (guest, COD)\n";
$co = req('GET', $base . '/checkout');
check('Checkout page loads', $co['code'] === 200 && no_php_errors($co['body']));
preg_match('/name="checkout_key" value="([a-f0-9]{64})"/', $co['body'], $km);
$key = $km[1] ?? '';
$q = json_decode(req('POST', $base . '/api/checkout-quote', ['city' => 'Lahore', 'region' => 'Punjab', 'shipping_method' => 'standard', 'payment_method' => 'cod', 'csrf_token' => $csrf], $ajax)['body'], true);
check('Shipping quote for Lahore', !empty($q['options']) && $q['options'][0]['name'] !== '', json_encode($q));
$order = [
    'csrf_token' => $csrf, 'checkout_key' => $key, 'email' => 'smoke+' . time() . '@example.com', 'phone' => '03001234567',
    'full_name' => 'Smoke Test', 'address_line1' => '12 Test Street, Gulberg', 'city' => 'Lahore', 'region' => 'Punjab',
    'shipping_method' => 'standard', 'payment_method' => 'cod', 'accept_terms' => 1,
    'regular_price' => 1, 'unit_price' => 1, 'grand_total' => 1,   // tampering attempts must be ignored
];
$r = req('POST', $base . '/checkout', $order);
check('Order placed → redirect to confirmation', $r['code'] === 302 && strpos($r['location'], '/order/') !== false, $r['code'] . ' ' . $r['location']);
$orderNumber = basename(parse_url($r['location'], PHP_URL_PATH));
$conf = req('GET', $base . '/order/' . $orderNumber);
check('Confirmation page shows order', $conf['code'] === 200 && strpos($conf['body'], $orderNumber) !== false);
$r2 = req('POST', $base . '/checkout', $order);
check('Duplicate submit returns the same order (no second order)', $r2['code'] === 302 && strpos($r2['location'], $orderNumber) !== false, $r2['location']);

echo "Admin\n";
$jarGuest = $jar;
$jar = tempnam(sys_get_temp_dir(), 'begletadm');
check('Admin requires login', strpos(req('GET', $base . '/admin/orders')['location'], '/admin/login') !== false);
$login = req('GET', $base . '/admin/login');
$r = req('POST', $base . '/admin/login', ['email' => $adminEmail, 'password' => $adminPass, 'csrf_token' => csrf_from($login['body'])]);
check('Admin login', $r['code'] === 302 && strpos($r['location'], '/admin/login') === false, $r['code'] . ' ' . $r['location']);
$profile = req('GET', $base . '/admin/');
if (strpos($profile['location'], 'profile') !== false) {
    echo "  (account has a temporary password — set a permanent one before running admin checks)\n";
} else {
    foreach (['', 'orders', 'products', 'product-edit?id=1', 'categories', 'collections', 'inventory', 'reviews', 'customers', 'newsletter', 'messages', 'coupons', 'homepage', 'section-edit?id=1', 'slides', 'slide-edit?id=1', 'testimonials', 'pages', 'theme', 'settings', 'shipping', 'shipping-zone?id=1', 'payments', 'transactions', 'seo', 'redirects', 'admins', 'roles', 'audit-log'] as $p) {
        $r = req('GET', $base . '/admin/' . $p);
        check("GET /admin/$p → 200", $r['code'] === 200 && no_php_errors($r['body']), (string) $r['code']);
    }
    $list = req('GET', $base . '/admin/orders?q=' . $orderNumber);
    preg_match('/order-view\?id=(\d+)/', $list['body'], $om);
    $oid = (int) ($om[1] ?? 0);
    $view = req('GET', $base . '/admin/order-view?id=' . $oid);
    $acsrf = csrf_from($view['body']);
    preg_match('/<dt class="fs-6 text-dark">Total<\/dt><dd[^>]*>([^<]+)</', $view['body'], $tm);
    check('Server ignored tampered prices (total is not Rs. 1)', isset($tm[1]) && trim($tm[1]) !== 'Rs. 1', $tm[1] ?? 'n/a');
    foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $st) {
        req('POST', $base . '/admin/order-view?id=' . $oid, ['action' => 'status', 'status' => $st, 'csrf_token' => $acsrf]);
    }
    req('POST', $base . '/admin/order-view?id=' . $oid, ['action' => 'cod_collected', 'csrf_token' => $acsrf]);
    $view = req('GET', $base . '/admin/order-view?id=' . $oid);
    check('Order delivered and COD collected', strpos($view['body'], '>Delivered<') !== false && strpos($view['body'], '>Paid<') !== false);
    $bad = req('POST', $base . '/admin/order-view?id=' . $oid, ['action' => 'status', 'status' => 'pending', 'csrf_token' => $acsrf]);
    check('Invalid status transition is refused', strpos(req('GET', $base . '/admin/order-view?id=' . $oid)['body'], '>Delivered<') !== false);
    $inv = req('GET', $base . '/admin/order-print?id=' . $oid . '&type=invoice');
    check('Invoice renders', $inv['code'] === 200 && strpos($inv['body'], $orderNumber) !== false);
    $csv = req('GET', $base . '/admin/orders-export?q=' . $orderNumber);
    check('CSV export', strpos($csv['body'], $orderNumber) !== false);
}
echo $fail ? "\n$fail check(s) FAILED\n" : "\nAll checks passed\n";
exit($fail ? 1 : 0);
