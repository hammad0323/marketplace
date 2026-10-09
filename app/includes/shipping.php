<?php
/**
 * Shipping zones & rates. A city is matched to a zone by exact city name,
 * then by region/province, then falls back to the default zone.
 */

function shipping_normalize(string $s): string
{
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}

function shipping_zone_for(string $city, string $region = ''): ?array
{
    $city = shipping_normalize($city);
    $region = shipping_normalize($region);
    if ($city !== '') {
        $z = db_one('SELECT z.* FROM shipping_zones z JOIN shipping_zone_locations l ON l.zone_id = z.id WHERE z.is_active = 1 AND LOWER(l.city) = ? ORDER BY z.sort_order LIMIT 1', [$city]);
        if ($z) {
            return $z;
        }
    }
    if ($region !== '') {
        $z = db_one("SELECT z.* FROM shipping_zones z JOIN shipping_zone_locations l ON l.zone_id = z.id WHERE z.is_active = 1 AND (l.city IS NULL OR l.city = '') AND LOWER(l.region) = ? ORDER BY z.sort_order LIMIT 1", [$region]);
        if ($z) {
            return $z;
        }
    }
    return db_one('SELECT * FROM shipping_zones WHERE is_active = 1 AND is_default = 1 ORDER BY sort_order LIMIT 1');
}

function shipping_rates_for_zone(int $zoneId): array
{
    $rates = db_all('SELECT * FROM shipping_rates WHERE zone_id = ? AND is_active = 1 ORDER BY FIELD(method, \'standard\', \'express\')', [$zoneId]);
    if (!setting_bool('express_enabled', true)) {
        $rates = array_values(array_filter($rates, fn($r) => $r['method'] !== 'express'));
    }
    return $rates;
}

/**
 * Compute shipping options for a destination and merchandise subtotal.
 * @return array{zone:?array, options:array, error:?string}
 */
function shipping_options(string $city, string $region, float $subtotal, bool $freeShippingCoupon = false): array
{
    $zone = shipping_zone_for($city, $region);
    if (!$zone) {
        // No zones configured: fall back to the global default charge.
        $fee = (float) setting('shipping_default_rate', '250');
        return ['zone' => null, 'error' => null, 'options' => [[
            'method' => 'standard',
            'name' => 'Standard delivery',
            'cost' => shipping_apply_free($fee, $subtotal, null, $freeShippingCoupon),
            'base_cost' => $fee,
            'estimate' => setting('shipping_default_estimate', '3–5 working days'),
        ]]];
    }
    $options = [];
    foreach (shipping_rates_for_zone((int) $zone['id']) as $r) {
        $options[] = [
            'method' => $r['method'],
            'name' => $r['name'],
            'base_cost' => (float) $r['rate'],
            'cost' => $r['method'] === 'standard'
                ? shipping_apply_free((float) $r['rate'], $subtotal, $r['free_over'] !== null ? (float) $r['free_over'] : null, $freeShippingCoupon)
                : (float) $r['rate'],
            'estimate' => $r['min_days'] === $r['max_days'] ? $r['min_days'] . ' working days' : $r['min_days'] . '–' . $r['max_days'] . ' working days',
        ];
    }
    if (!$options) {
        return ['zone' => $zone, 'options' => [], 'error' => 'Delivery is not currently available to this location.'];
    }
    return ['zone' => $zone, 'options' => $options, 'error' => null];
}

function shipping_apply_free(float $fee, float $subtotal, ?float $zoneFreeOver, bool $coupon): float
{
    if ($coupon) {
        return 0.0;
    }
    if (setting_bool('free_shipping_enabled', true)) {
        $threshold = $zoneFreeOver ?? (float) setting('free_shipping_threshold', '0');
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }
    }
    return $fee;
}

/** Whether COD can be offered to this destination & order value. */
function cod_available_for(?array $zone, float $total): array
{
    $gw = payment_gateway('cod');
    if (!$gw || !(int) $gw['is_enabled']) {
        return [false, 'Cash on delivery is not available.'];
    }
    if ($zone && !(int) $zone['cod_available']) {
        return [false, 'Cash on delivery is not available in your area.'];
    }
    $cfg = gateway_config($gw);
    $max = (float) ($cfg['max_order_amount'] ?? 0);
    if ($max > 0 && $total > $max) {
        return [false, 'Cash on delivery is available for orders up to ' . money($max) . '.'];
    }
    return [true, null];
}

function cod_fee(): float
{
    $gw = payment_gateway('cod');
    return $gw ? (float) (gateway_config($gw)['fee'] ?? 0) : 0.0;
}

function pk_regions(): array
{
    $custom = array_filter(array_map('trim', explode("\n", (string) setting('checkout_regions', ''))));
    return $custom ?: ['Punjab', 'Sindh', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Jammu & Kashmir'];
}
