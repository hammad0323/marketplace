<?php
/**
 * Delivery zones, rates, free-delivery thresholds and delivery estimates.
 * Matching order: city → region (province) → default zone.
 */

function shipping_zone_for(string $city, string $region): ?array
{
    $city = trim($city);
    $region = trim($region);
    if ($city !== '') {
        $z = db_row(
            "SELECT z.* FROM shipping_zones z JOIN shipping_zone_locations l ON l.zone_id = z.id
             WHERE z.is_active = 1 AND l.location_type = 'city' AND LOWER(l.name) = LOWER(?) ORDER BY z.sort_order LIMIT 1",
            [$city]
        );
        if ($z) {
            return $z;
        }
    }
    if ($region !== '') {
        $z = db_row(
            "SELECT z.* FROM shipping_zones z JOIN shipping_zone_locations l ON l.zone_id = z.id
             WHERE z.is_active = 1 AND l.location_type = 'region' AND LOWER(l.name) = LOWER(?) ORDER BY z.sort_order LIMIT 1",
            [$region]
        );
        if ($z) {
            return $z;
        }
    }
    return db_row('SELECT * FROM shipping_zones WHERE is_active = 1 AND is_default = 1 ORDER BY sort_order LIMIT 1');
}

function shipping_rates_for_zone(int $zoneId): array
{
    return db_all('SELECT * FROM shipping_rates WHERE zone_id = ? AND is_active = 1 ORDER BY FIELD(method, "standard", "express")', [$zoneId]);
}

/** Business-day date range label, e.g. "Mon 27 Oct – Wed 29 Oct". */
function delivery_estimate_label(int $daysMin, int $daysMax): string
{
    $add = function (int $days): int {
        $t = time();
        while ($days > 0) {
            $t += 86400;
            if ((int) date('N', $t) !== 7) { // Sundays excluded
                $days--;
            }
        }
        return $t;
    };
    $a = $add(max(0, $daysMin));
    $b = $add(max($daysMin, $daysMax));
    return date('D j M', $a) . ($b !== $a ? ' – ' . date('D j M', $b) : '');
}

/**
 * Urgent (city-wise) delivery rate for a city, or null. Orders after the cut-off time
 * move one day later.
 */
function urgent_rate_for(string $city): ?array
{
    if (!setting_bool('urgent_enabled', true) || trim($city) === '') {
        return null;
    }
    $r = db_row('SELECT * FROM urgent_delivery_rates WHERE is_active = 1 AND LOWER(city) = LOWER(?)', [trim($city)]);
    if (!$r) {
        return null;
    }
    $late = $r['cutoff_time'] && date('H:i:s') > $r['cutoff_time'];
    $min = (int) $r['days_min'];
    $max = max((int) $r['days_max'], $min);
    $r['days_min'] = $min + ($late ? 1 : 0);
    $r['days_max'] = $max + ($late ? 1 : 0);
    $r['late'] = $late;
    $r['estimate'] = $r['days_max'] === 0 ? 'Today' : delivery_estimate_label($r['days_min'], $r['days_max']);
    if ($late) {
        $r['estimate'] .= ' (order before ' . date('g:i a', strtotime($r['cutoff_time'])) . ' for ' . ($min === 0 ? 'same-day' : 'faster') . ' delivery)';
    }
    return $r;
}

function urgent_cities(): array
{
    return setting_bool('urgent_enabled', true) ? db_all('SELECT * FROM urgent_delivery_rates WHERE is_active = 1 ORDER BY sort_order, city') : [];
}

/**
 * Returns the delivery fee and estimate for a destination.
 * $leadDays: production lead time of made-to-order / custom items (added to the estimate).
 * $urgentAllowed: every item in the order may ship urgently (no custom / made-to-order items).
 */
function shipping_quote(string $city, string $region, float $subtotal, string $method = 'standard', int $leadDays = 0, bool $urgentAllowed = false): array
{
    $zone = shipping_zone_for($city, $region);
    $globalFree = (float) setting('free_shipping_threshold', 0);
    $defaultFee = (float) setting('default_shipping_fee', 250);
    $result = [
        'zone' => $zone['name'] ?? 'Standard',
        'zone_id' => $zone ? (int) $zone['id'] : null,
        'method' => 'standard',
        'label' => 'Standard Delivery',
        'fee' => $defaultFee,
        'free' => false,
        'min_order' => null,
        'cod_available' => $zone ? (bool) $zone['cod_enabled'] : true,
        'days_min' => 3 + $leadDays,
        'days_max' => 5 + $leadDays,
        'methods' => [],
        'free_threshold' => $globalFree ?: null,
        'free_above' => null,
    ];
    if ($zone) {
        foreach (shipping_rates_for_zone((int) $zone['id']) as $r) {
            if ($r['method'] === 'express' && !setting_bool('express_enabled', true)) {
                continue;
            }
            $fee = (float) $r['fee'];
            $freeAbove = $r['free_above'] !== null ? (float) $r['free_above'] : null;
            $isFree = ($freeAbove !== null && $subtotal >= $freeAbove) || ($r['method'] === 'standard' && $globalFree > 0 && $subtotal >= $globalFree);
            $entry = [
                'method' => $r['method'], 'label' => $r['label'], 'fee' => $isFree ? 0.0 : $fee, 'base_fee' => $fee, 'free' => $isFree,
                'days_min' => (int) $r['days_min'] + $leadDays, 'days_max' => (int) $r['days_max'] + $leadDays,
                'min_order' => $r['min_order'] !== null ? (float) $r['min_order'] : null, 'free_above' => $freeAbove,
            ];
            $entry['estimate'] = delivery_estimate_label($entry['days_min'], $entry['days_max']);
            $result['methods'][$r['method']] = $entry;
        }
    } elseif ($globalFree > 0 && $subtotal >= $globalFree) {
        $result['fee'] = 0.0;
        $result['free'] = true;
    }
    if ($urgentAllowed && ($u = urgent_rate_for($city))) {
        // Urgent delivery is a paid service: the free-delivery threshold does not apply.
        $result['methods']['urgent'] = [
            'method' => 'urgent', 'label' => $u['label'], 'fee' => (float) $u['fee'], 'base_fee' => (float) $u['fee'], 'free' => false,
            'days_min' => $u['days_min'], 'days_max' => $u['days_max'], 'min_order' => null, 'free_above' => null, 'estimate' => $u['estimate'],
        ];
    }
    $chosen = $result['methods'][$method] ?? ($result['methods']['standard'] ?? ($result['methods'] ? reset($result['methods']) : null));
    if ($chosen) {
        $result = array_merge($result, $chosen);
        $result['free_threshold'] = $chosen['method'] === 'urgent' ? null : ($chosen['free_above'] ?? ($globalFree ?: null));
    }
    $result['estimate'] = $result['estimate'] ?? delivery_estimate_label($result['days_min'], $result['days_max']);
    if (($result['method'] ?? '') !== 'urgent') {
        $result['estimate'] = delivery_estimate_label($result['days_min'], $result['days_max']);
    }
    return $result;
}

function pakistan_regions(): array
{
    $custom = array_filter(array_map('trim', explode("\n", (string) setting('checkout_regions', ''))));
    return $custom ?: ['Punjab', 'Sindh', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Jammu & Kashmir'];
}
