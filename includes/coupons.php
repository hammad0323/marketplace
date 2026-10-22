<?php
/**
 * Coupon validation. Always evaluated server-side against the live cart total.
 */

function coupon_find(string $code): ?array
{
    return db_row('SELECT * FROM coupons WHERE code = ?', [strtoupper(trim($code))]);
}

/**
 * Returns ['ok' => bool, 'message' => string, 'coupon' => row, 'discount' => float, 'free_shipping' => bool]
 */
function coupon_evaluate(string $code, float $subtotal, ?string $email = null, ?int $customerId = null): array
{
    $fail = fn(string $m) => ['ok' => false, 'message' => $m, 'coupon' => null, 'discount' => 0.0, 'free_shipping' => false];
    $c = coupon_find($code);
    if (!$c || !(int) $c['is_active']) {
        return $fail('This coupon code is not valid.');
    }
    $now = time();
    if ($c['starts_at'] && strtotime($c['starts_at']) > $now) {
        return $fail('This coupon is not active yet.');
    }
    if ($c['expires_at'] && strtotime($c['expires_at']) < $now) {
        return $fail('This coupon has expired.');
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return $fail('This coupon has reached its usage limit.');
    }
    if ($c['min_order'] !== null && $subtotal < (float) $c['min_order']) {
        return $fail('Spend at least ' . money($c['min_order']) . ' to use this coupon.');
    }
    if ($c['usage_limit_per_customer'] !== null && ($email || $customerId)) {
        $used = (int) db_val(
            "SELECT COUNT(*) FROM coupon_usage cu JOIN orders o ON o.id = cu.order_id
             WHERE cu.coupon_id = ? AND o.status NOT IN ('cancelled') AND (cu.email = ? OR (cu.customer_id IS NOT NULL AND cu.customer_id = ?))",
            [(int) $c['id'], strtolower((string) $email), (int) $customerId]
        );
        if ($used >= (int) $c['usage_limit_per_customer']) {
            return $fail('You have already used this coupon.');
        }
    }
    $discount = 0.0;
    $free = false;
    switch ($c['discount_type']) {
        case 'percent':
            $discount = $subtotal * min(100, (float) $c['discount_value']) / 100;
            if ($c['max_discount'] !== null) {
                $discount = min($discount, (float) $c['max_discount']);
            }
            break;
        case 'fixed':
            $discount = min($subtotal, (float) $c['discount_value']);
            break;
        case 'free_shipping':
            $free = true;
            break;
    }
    return ['ok' => true, 'message' => 'Coupon applied.', 'coupon' => $c, 'discount' => round($discount, 2), 'free_shipping' => $free];
}
