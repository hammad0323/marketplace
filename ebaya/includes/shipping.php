<?php
/** Delivery zones, rates, COD availability and delivery estimates. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function shipping_zone_for(string $city, string $province = ''): ?array
{
    $city = trim($city);
    $zone = null;
    if ($city !== '') {
        $zone = db_one("SELECT z.* FROM shipping_zone_locations l JOIN shipping_zones z ON z.id = l.zone_id
                        WHERE l.location_type = 'city' AND l.name = ? AND z.status = 'active' LIMIT 1", [$city]);
    }
    if (!$zone && trim($province) !== '') {
        $zone = db_one("SELECT z.* FROM shipping_zone_locations l JOIN shipping_zones z ON z.id = l.zone_id
                        WHERE l.location_type = 'province' AND l.name = ? AND z.status = 'active' LIMIT 1", [trim($province)]);
    }
    if (!$zone) {
        $zone = db_one("SELECT * FROM shipping_zones WHERE is_default = 1 AND status = 'active' LIMIT 1");
    }
    return $zone;
}

/** Cities configured in any zone (for checkout autocomplete). */
function shipping_known_cities(): array
{
    return db_col("SELECT l.name FROM shipping_zone_locations l JOIN shipping_zones z ON z.id = l.zone_id
                   WHERE l.location_type = 'city' AND z.status = 'active' ORDER BY l.name");
}

function shipping_provinces(): array
{
    return ['Punjab', 'Sindh', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Jammu & Kashmir'];
}

function cart_has_made_to_order(array $lines): bool
{
    foreach ($lines as $l) {
        if ($l['fulfillment_type'] === 'made_to_order' || $l['customised']) return true;
    }
    return false;
}

function cart_max_lead_days(array $lines): int
{
    $max = 0;
    foreach ($lines as $l) {
        if ($l['fulfillment_type'] === 'made_to_order' || $l['customised']) $max = max($max, $l['lead_days']);
    }
    return $max;
}

/**
 * Available delivery methods for a zone and cart.
 * Returns [method => [method, label, cost, free, est_min, est_max, est_text, min_order]]
 */
function shipping_options(?array $zone, array $lines, float $merchandiseTotal): array
{
    if (!$zone) return [];
    $mto = cart_has_made_to_order($lines);
    $lead = cart_max_lead_days($lines);
    $rates = db_all("SELECT * FROM shipping_rates WHERE zone_id = ? AND status = 'active' ORDER BY method, FIELD(applies_to,'made_to_order','ready_to_ship','all')", [(int)$zone['id']]);
    $pick = [];
    foreach ($rates as $r) {
        $m = $r['method'];
        $fits = $r['applies_to'] === 'all' || ($r['applies_to'] === 'made_to_order' && $mto) || ($r['applies_to'] === 'ready_to_ship' && !$mto);
        if ($fits && !isset($pick[$m])) $pick[$m] = $r;
    }
    $out = [];
    foreach ($pick as $m => $r) {
        $free = $r['free_over'] !== null && (float)$r['free_over'] > 0 && $merchandiseTotal >= (float)$r['free_over'];
        $estMin = (int)$r['est_days_min'] + $lead;
        $estMax = (int)$r['est_days_max'] + $lead;
        $out[$m] = [
            'method' => $m,
            'label' => $r['label'],
            'cost' => $free ? 0.0 : (float)$r['rate'],
            'free' => $free,
            'free_over' => $r['free_over'] !== null ? (float)$r['free_over'] : null,
            'min_order' => $r['min_order'] !== null ? (float)$r['min_order'] : null,
            'est_min' => $estMin,
            'est_max' => $estMax,
            'est_text' => delivery_estimate_text($estMin, $estMax),
            'lead_days' => $lead,
        ];
    }
    return $out;
}

/** Working-day estimate (skips Sundays) → "Tue 14 Oct – Fri 17 Oct". */
function delivery_estimate_text(int $minDays, int $maxDays): string
{
    $add = function (int $days): DateTime {
        $d = new DateTime('today');
        while ($days > 0) {
            $d->modify('+1 day');
            if ($d->format('N') != 7) $days--;
        }
        return $d;
    };
    $a = $add(max(1, $minDays));
    $b = $add(max($minDays, $maxDays, 1));
    return $a == $b ? $a->format('D j M') : $a->format('D j M') . ' – ' . $b->format('D j M');
}

/** Default estimate shown on product pages (default zone, standard method). */
function product_delivery_estimate(array $product): ?string
{
    $zone = db_one("SELECT * FROM shipping_zones WHERE is_default = 1 AND status = 'active' LIMIT 1");
    if (!$zone) return null;
    $line = ['fulfillment_type' => $product['fulfillment_type'], 'customised' => false, 'lead_days' => (int)$product['production_lead_days']];
    $opts = shipping_options($zone, [$line], product_price($product));
    return isset($opts['standard']) ? $opts['standard']['est_text'] : null;
}

function cod_available(?array $zone, float $orderTotal): bool
{
    if (!$zone || !$zone['cod_enabled']) return false;
    if ($zone['cod_max_order'] !== null && (float)$zone['cod_max_order'] > 0 && $orderTotal > (float)$zone['cod_max_order']) return false;
    return payment_gateway_available('cod');
}
