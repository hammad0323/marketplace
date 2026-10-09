<?php
/** Cart AJAX endpoint: add, update, remove, coupon, render. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$action = post('action', get('action', 'render'));
if ($action !== 'render') {
    if (!is_post()) json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
    csrf_check();
    rate_limit_or_fail('cart', 120, 60);
}

$message = null;
try {
    switch ($action) {
        case 'add':
            cart_add((int)post('product_id'), (int)post('variant_id'), (int)post('qty', 1), [
                'custom_length' => post('custom_length'), 'custom_sleeve' => post('custom_sleeve'), 'custom_notes' => post('custom_notes'),
            ]);
            $message = 'Added to your bag';
            break;
        case 'update':
            cart_update_qty((int)post('item_id'), (int)post('qty'));
            break;
        case 'remove':
            cart_remove((int)post('item_id'));
            break;
        case 'coupon':
            $code = strtoupper(mb_substr(post('code'), 0, 50));
            $lines = cart_lines();
            $sum = array_sum(array_map(fn($l) => ($l['unit_price'] + $l['custom_fee']) * $l['qty'], $lines));
            [$c, $err] = coupon_evaluate($code, $sum, current_customer()['email'] ?? '', customer_id());
            if (!$c) json_out(['ok' => false, 'message' => $err ?: 'Invalid code']);
            cart_set_coupon($c['code']);
            $message = 'Coupon applied';
            break;
        case 'remove_coupon':
            cart_set_coupon(null);
            break;
        case 'render':
            break;
        default:
            json_out(['ok' => false, 'message' => 'Unknown action'], 400);
    }
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'message' => $e->getMessage(), 'count' => cart_count()]);
}

$lines = cart_lines();
$totals = checkout_totals($lines, ['coupon' => cart_coupon(), 'email' => current_customer()['email'] ?? '', 'customer_id' => customer_id()]);
ob_start();
$mini = true;
include ROOT_PATH . '/templates/cart-contents.php';
$miniHtml = ob_get_clean();
ob_start();
$mini = false;
include ROOT_PATH . '/templates/cart-contents.php';
$pageHtml = ob_get_clean();

json_out(['ok' => true, 'message' => $message, 'count' => cart_count(), 'mini' => $miniHtml, 'page' => $pageHtml]);
