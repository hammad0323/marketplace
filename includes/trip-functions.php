<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function generate_trip_days($conn, $tripId, $dateFrom, $dateTo)
{
    db_execute($conn, 'DELETE FROM trip_days WHERE trip_id = ?', [(int) $tripId]);
    if (!$dateFrom) {
        db_execute($conn, 'INSERT INTO trip_days (trip_id, day_number) VALUES (?, 1)', [(int) $tripId]);
        return;
    }
    $start = strtotime($dateFrom);
    $end = $dateTo ? strtotime($dateTo) : $start;
    $dayNum = 1;
    for ($ts = $start; $ts <= $end; $ts = strtotime('+1 day', $ts)) {
        db_execute($conn, 'INSERT INTO trip_days (trip_id, day_number, day_date) VALUES (?, ?, ?)', [(int) $tripId, $dayNum, date('Y-m-d', $ts)]);
        $dayNum++;
    }
}

/**
 * Categorizes linked services into hotel/transport/food/activities buckets
 * by category name (heuristic — works with the seeded categories and any
 * admin adds later since it falls back to "activities"), adds a baseline
 * per-person daily food estimate, applies platform fee + tax, and returns
 * Economy/Standard/Luxury tiers for the animated budget dashboard.
 */
function recalculate_trip_budget($conn, $tripId)
{
    $trip = db_select_one($conn, 'SELECT * FROM trips WHERE id = ?', [(int) $tripId]);
    if (!$trip) {
        return null;
    }

    $days = max(1, db_count($conn, 'SELECT COUNT(*) FROM trip_days WHERE trip_id = ?', [(int) $tripId]));
    $people = max(1, (int) $trip['adults'] + (int) $trip['children']);

    $items = db_select(
        $conn,
        'SELECT s.price, s.price_unit, cat.name AS category_name
         FROM trip_items ti JOIN trip_days td ON td.id = ti.trip_day_id
         LEFT JOIN services s ON s.id = ti.service_id LEFT JOIN categories cat ON cat.id = s.category_id
         WHERE td.trip_id = ? AND ti.service_id IS NOT NULL',
        [(int) $tripId]
    );

    $hotel = 0.0;
    $transport = 0.0;
    $activities = 0.0;
    $foodFromServices = 0.0;

    foreach ($items as $item) {
        $name = strtolower((string) $item['category_name']);
        $price = (float) $item['price'];
        if (strpos($name, 'hotel') !== false || strpos($name, 'villa') !== false || strpos($name, 'guest house') !== false || strpos($name, 'apartment') !== false) {
            $hotel += $price;
        } elseif (strpos($name, 'car') !== false || strpos($name, 'transfer') !== false || strpos($name, 'bus') !== false || strpos($name, 'coach') !== false || strpos($name, 'driver') !== false) {
            $transport += $price;
        } elseif (strpos($name, 'restaurant') !== false || strpos($name, 'cafe') !== false) {
            $foodFromServices += $price;
        } else {
            $activities += $price;
        }
    }

    $dailyFoodRate = ['economy' => 20, 'standard' => 40, 'luxury' => 80][$trip['budget_mode']] ?? 40;
    $food = $foodFromServices + ($dailyFoodRate * $people * $days);

    $feePercent = (float) get_setting($conn, 'service_fee_percent', 3);
    $taxPercent = (float) (db_select_one($conn, 'SELECT percent FROM taxes WHERE is_active = 1 ORDER BY id LIMIT 1')['percent'] ?? 5);

    $subtotal = $hotel + $transport + $food + $activities;
    $fees = round($subtotal * $feePercent / 100, 2);
    $tax = round($subtotal * $taxPercent / 100, 2);
    $total = round($subtotal + $fees + $tax, 2);

    db_execute($conn, 'DELETE FROM trip_budget WHERE trip_id = ?', [(int) $tripId]);
    db_execute(
        $conn,
        'INSERT INTO trip_budget (trip_id, hotel_total, transport_total, food_total, activities_total, fees_total, tax_total, estimated_total) VALUES (?,?,?,?,?,?,?,?)',
        [(int) $tripId, round($hotel, 2), round($transport, 2), round($food, 2), round($activities, 2), $fees, $tax, $total]
    );

    return [
        'hotel' => round($hotel, 2), 'transport' => round($transport, 2), 'food' => round($food, 2), 'activities' => round($activities, 2),
        'fees' => $fees, 'tax' => $tax, 'total' => $total,
        'minimum' => round($total * 0.75, 2), 'recommended' => $total, 'premium' => round($total * 1.4, 2),
        'days' => $days, 'people' => $people,
    ];
}
