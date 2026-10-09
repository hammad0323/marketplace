<?php
/**
 * Coupon validation & discount calculation.
 */

function coupon_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    return $code === '' ? null : db_one('SELECT * FROM coupons WHERE code = ?', [$code]);
}

/**
 * Validate a coupon against a subtotal and customer.
 * @return array{0:?array,1:?string} [coupon, error]
 */
function coupon_validate(string $code, float $subtotal, ?int $customerId, ?string $email): array
{
    $c = coupon_by_code($code);
    if (!$c || !(int) $c['is_active']) {
        return [null, 'This coupon code is not valid.'];
    }
    $now = time();
    if ($c['starts_at'] && strtotime($c['starts_at']) > $now) {
        return [null, 'This coupon is not active yet.'];
    }
    if ($c['ends_at'] && strtotime($c['ends_at']) < $now) {
        return [null, 'This coupon has expired.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['times_used'] >= (int) $c['usage_limit']) {
        return [null, 'This coupon has reached its usage limit.'];
    }
    if ((float) $c['min_order_amount'] > 0 && $subtotal < (float) $c['min_order_amount']) {
        return [null, 'Spend at least ' . money($c['min_order_amount']) . ' to use this coupon.'];
    }
    if ($c['per_customer_limit'] !== null && ($customerId || $email)) {
        $used = (int) db_val(
            'SELECT COUNT(*) FROM coupon_usage cu JOIN orders o ON o.id = cu.order_id
             WHERE cu.coupon_id = ? AND o.status NOT IN (\'cancelled\') AND (cu.email = ? OR (cu.customer_id IS NOT NULL AND cu.customer_id = ?))',
            [(int) $c['id'], mb_strtolower((string) $email), (int) $customerId]
        );
        if ($used >= (int) $c['per_customer_limit']) {
            return [null, 'You have already used this coupon.'];
        }
    }
    return [$c, null];
}

function coupon_discount(array $c, float $subtotal): float
{
    switch ($c['discount_type']) {
        case 'percent':
            $d = $subtotal * min(100, (float) $c['discount_value']) / 100;
            if ($c['max_discount'] !== null && (float) $c['max_discount'] > 0) {
                $d = min($d, (float) $c['max_discount']);
            }
            break;
        case 'fixed':
            $d = (float) $c['discount_value'];
            break;
        default:
            $d = 0.0;
    }
    return round(min($d, $subtotal), 2);
}

function coupon_summary(array $c): string
{
    switch ($c['discount_type']) {
        case 'percent':
            return rtrim(rtrim(number_format((float) $c['discount_value'], 2), '0'), '.') . '% off';
        case 'fixed':
            return money($c['discount_value']) . ' off';
        default:
            return 'Free delivery';
    }
}
