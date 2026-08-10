<?php
/**
 * Shared booking/availability logic used by the public booking form,
 * the provider calendar, and both dashboards.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function generate_booking_ref()
{
    return 'WD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * True if every date in [dateFrom, dateTo) is free of a blocked/reserved row.
 * Absence of a row means the date is available by default.
 */
function service_is_available_range($conn, $serviceId, $dateFrom, $dateTo)
{
    $dateTo = $dateTo ?: $dateFrom;
    $conflict = db_select_one(
        $conn,
        'SELECT id FROM service_availability WHERE service_id = ? AND date >= ? AND date < ? AND status IN ("blocked", "reserved") LIMIT 1',
        [(int) $serviceId, $dateFrom, $dateTo]
    );
    return !$conflict;
}

function mark_service_dates($conn, $serviceId, $dateFrom, $dateTo, $status)
{
    $dateTo = $dateTo ?: $dateFrom;
    $current = strtotime($dateFrom);
    $end = strtotime($dateTo);
    while ($current < $end || ($current === $end && $dateFrom === $dateTo)) {
        $d = date('Y-m-d', $current);
        $existing = db_select_one($conn, 'SELECT id FROM service_availability WHERE service_id = ? AND date = ?', [(int) $serviceId, $d]);
        if ($existing) {
            db_execute($conn, 'UPDATE service_availability SET status = ? WHERE id = ?', [$status, (int) $existing['id']]);
        } else {
            db_execute($conn, 'INSERT INTO service_availability (service_id, date, status) VALUES (?, ?, ?)', [(int) $serviceId, $d, $status]);
        }
        $current = strtotime('+1 day', $current);
        if ($dateFrom === $dateTo) {
            break;
        }
    }
}

/**
 * Computes a full price breakdown for a prospective booking.
 * @return array{units:float, base:float, tax:float, fee:float, commission:float, total:float}
 */
function calculate_booking_price($conn, array $service, $units)
{
    $units = max(1, (float) $units);
    $base = round((float) $service['price'] * $units, 2);

    $taxPercent = (float) (db_select_one($conn, 'SELECT percent FROM taxes WHERE applies_to IN ("all","booking") AND is_active = 1 ORDER BY id LIMIT 1')['percent'] ?? 0);
    $feePercent = (float) get_setting($conn, 'service_fee_percent', 0);

    $commissionRow = db_select_one($conn, 'SELECT rate_percent FROM commissions WHERE service_id = ? AND is_active = 1', [(int) $service['id']])
        ?: db_select_one($conn, 'SELECT rate_percent FROM commissions WHERE category_id = ? AND is_active = 1', [(int) $service['category_id']]);
    $commissionPercent = $commissionRow ? (float) $commissionRow['rate_percent'] : (float) get_setting($conn, 'default_commission_percent', 10);

    $tax = round($base * $taxPercent / 100, 2);
    $fee = round($base * $feePercent / 100, 2);
    $commission = round($base * $commissionPercent / 100, 2);
    $total = round($base + $tax + $fee, 2);

    return ['units' => $units, 'base' => $base, 'tax' => $tax, 'fee' => $fee, 'commission' => $commission, 'total' => $total, 'tax_percent' => $taxPercent, 'fee_percent' => $feePercent];
}

/**
 * Reads date/time/quantity fields out of a request array (works for both
 * $_GET on the price-preview endpoint and $_POST on the real submit) and
 * converts them into a pricing "unit" count matching the service's
 * price_unit. Returns null when the request doesn't have what it needs.
 */
function booking_units_from_request(array $service, array $params)
{
    $unit = $service['price_unit'];
    $dateFrom = clean_input($params['date_from'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        return null;
    }

    if ($unit === 'night' || $unit === 'day') {
        $dateTo = clean_input($params['date_to'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) || $dateTo <= $dateFrom) {
            return null;
        }
        $days = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
        return max(1, (float) $days);
    }

    if ($unit === 'hour') {
        $start = clean_input($params['start_time'] ?? '');
        $end = clean_input($params['end_time'] ?? '');
        if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            return null;
        }
        $hours = (strtotime($end) - strtotime($start)) / 3600;
        return $hours > 0 ? $hours : null;
    }

    if ($unit === 'person') {
        $guests = max(1, (int) ($params['guests'] ?? 1));
        return $guests;
    }

    // fixed
    return max(1, (int) ($params['quantity'] ?? 1));
}

/**
 * Booking status transitions allowed from each current status, per actor.
 */
function booking_allowed_transitions($status, $actor)
{
    $map = [
        'provider' => [
            'pending' => ['accepted', 'rejected'],
            'accepted' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled'],
        ],
        'customer' => [
            'pending' => ['cancelled'],
            'accepted' => ['cancelled'],
        ],
    ];
    return $map[$actor][$status] ?? [];
}
