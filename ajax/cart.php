<?php
/** Cart actions: add, update, remove, coupon, drawer/page HTML. */
require __DIR__ . '/_init.php';

$action = input('action', 'drawer');
$response = ['ok' => true];

if ($action !== 'drawer' && $action !== 'page') {
    ajax_require_post();
    if (!rate_limit('cart:' . client_ip(), 120, 60)) {
        json_response(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
    }
}

switch ($action) {
    case 'urgent_pref':
        // Remembered so checkout pre-selects urgent delivery for this city.
        $_SESSION['urgent_city'] = mb_substr(input('city', '', 'post'), 0, 100);
        json_response(['ok' => true]);
    case 'add':
        $custom = [
            'custom_measurements' => $_POST['custom_measurements'] ?? '', 'custom_color' => $_POST['custom_color'] ?? '',
            'personalization' => $_POST['personalization'] ?? '', 'custom_instructions' => $_POST['custom_instructions'] ?? '',
        ];
        $variantId = input_int('variant_id') ?: null;
        $res = cart_add(input_int('product_id'), $variantId, max(1, input_int('qty', 1)), array_map(fn($v) => is_string($v) ? $v : '', $custom));
        $response = $res;
        break;
    case 'update':
        $response = cart_update_qty(input_int('item_id'), input_int('qty'));
        break;
    case 'remove':
        cart_remove(input_int('item_id'));
        $response = ['ok' => true, 'message' => 'Item removed.'];
        break;
    case 'coupon':
        $code = strtoupper(substr(input('code', '', 'post'), 0, 40));
        if ($code === '') {
            cart_set_coupon(null);
            $response = ['ok' => true, 'message' => 'Coupon removed.'];
            break;
        }
        if (!rate_limit('coupon:' . client_ip(), 15, 600)) {
            json_response(['ok' => false, 'message' => 'Too many coupon attempts. Please try later.'], 429);
        }
        $lines = cart_lines();
        $sub = array_sum(array_map(fn($l) => ($l['unit_price'] + $l['customization_fee']) * $l['qty'], $lines));
        $res = coupon_evaluate($code, $sub, input('email', '', 'post') ?: (current_customer()['email'] ?? null), customer_id());
        if ($res['ok']) {
            cart_set_coupon($res['coupon']['code']);
        }
        $response = ['ok' => $res['ok'], 'message' => $res['ok'] ? 'Coupon “' . $res['coupon']['code'] . '” applied.' : $res['message']];
        break;
}

$variant = input('view', 'drawer') === 'page' ? 'page' : 'drawer';
$response['count'] = cart_count();
$response['html'] = render_partial('components/cart-contents', ['variant' => $variant]);
json_response($response);
