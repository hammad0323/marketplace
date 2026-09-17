<?php
/**
 * chatbot-functions.php — rule-based (keyword/intent) database assistant.
 * English, Urdu and Roman Urdu queries about bookings/availability/payments.
 * NEVER invents data: every answer is built from a DB query result; if
 * nothing is understood or nothing is found, it says so plainly.
 * No external AI API is used or required.
 */

const WH_MONTH_MAP = [
    'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3,
    'april' => 4, 'apr' => 4, 'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
    'august' => 8, 'aug' => 8, 'september' => 9, 'sept' => 9, 'sep' => 9,
    'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'december' => 12, 'dec' => 12,
];

const WH_SLOT_SYNONYMS = [
    'morning' => ['morning', 'subah', 'subha'],
    'evening' => ['evening', 'shaam', 'sham'],
    'night' => ['night', 'raat', 'rat'],
];

function wh_chatbot_find_month_token($text)
{
    $monthNames = implode('|', array_keys(WH_MONTH_MAP));
    if (preg_match('/\b(' . $monthNames . ')\b/i', $text, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

/** Extracts a specific calendar date (day + month [+ year]) from free text. */
function wh_chatbot_extract_date($text)
{
    $today = new DateTime('today');
    $lower = strtolower($text);

    if (preg_match('/\baaj\b|\btoday\b/i', $lower)) {
        return $today->format('Y-m-d');
    }
    if (preg_match('/\bkal\b|\btomorrow\b/i', $lower)) {
        return (clone $today)->modify('+1 day')->format('Y-m-d');
    }
    if (preg_match('/\bparso\b/i', $lower)) {
        return (clone $today)->modify('+2 day')->format('Y-m-d');
    }

    // yyyy-mm-dd
    if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $lower, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    // dd/mm/yyyy or dd-mm-yyyy
    if (preg_match('#\b(\d{1,2})[/-](\d{1,2})[/-](\d{4})\b#', $lower, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    }

    $monthNames = implode('|', array_keys(WH_MONTH_MAP));
    // "25 december 2026" / "25 dec"
    if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $monthNames . ')[a-z]*\.?\s*(\d{4})?\b/i', $lower, $m)) {
        $day = (int) $m[1];
        $month = WH_MONTH_MAP[strtolower($m[2])] ?? null;
        $year = !empty($m[3]) ? (int) $m[3] : null;
        return wh_chatbot_build_date($day, $month, $year);
    }
    // "december 25" / "dec 25, 2026"
    if (preg_match('/\b(' . $monthNames . ')[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(\d{4})?\b/i', $lower, $m)) {
        $day = (int) $m[2];
        $month = WH_MONTH_MAP[strtolower($m[1])] ?? null;
        $year = !empty($m[3]) ? (int) $m[3] : null;
        return wh_chatbot_build_date($day, $month, $year);
    }

    return null;
}

function wh_chatbot_build_date($day, $month, $year)
{
    if (!$day || !$month || $day < 1 || $day > 31 || $month < 1 || $month > 12) {
        return null;
    }
    $today = new DateTime('today');
    if (!$year) {
        $year = (int) $today->format('Y');
        $candidate = DateTime::createFromFormat('Y-n-j', "$year-$month-$day");
        if ($candidate && $candidate < $today) {
            $year++;
        }
    }
    $date = DateTime::createFromFormat('Y-n-j', "$year-$month-$day");
    return $date ? $date->format('Y-m-d') : null;
}

/** Extracts a whole month (no specific day) — "December", "is month", "next month". */
function wh_chatbot_extract_month($text)
{
    $lower = strtolower($text);
    $today = new DateTime('today');

    if (preg_match('/\bis month\b|\bthis month\b/i', $lower)) {
        return ['year' => (int) $today->format('Y'), 'month' => (int) $today->format('n')];
    }
    if (preg_match('/\bnext month\b|\bagle mahine\b|\baglay mahine\b/i', $lower)) {
        $next = (clone $today)->modify('first day of next month');
        return ['year' => (int) $next->format('Y'), 'month' => (int) $next->format('n')];
    }

    $token = wh_chatbot_find_month_token($lower);
    if ($token) {
        // Only treat as a bare month if there's no adjacent day number (else wh_chatbot_extract_date already caught it).
        $month = WH_MONTH_MAP[$token];
        $year = (int) $today->format('Y');
        if ($month < (int) $today->format('n')) {
            $year++;
        }
        return ['year' => $year, 'month' => $month];
    }
    return null;
}

/** Finds a hall the business actually has, by fuzzy name match against the query text. */
function wh_chatbot_extract_hall($text, $halls)
{
    $lower = strtolower($text);
    $stopWords = ['hall', 'banquet', 'marriage', 'wedding', 'event', 'the', 'ka', 'ki', 'ke', 'mein', 'main'];
    foreach ($halls as $hall) {
        if (strpos($lower, strtolower($hall['name'])) !== false) {
            return $hall;
        }
    }
    foreach ($halls as $hall) {
        $words = preg_split('/\s+/', strtolower($hall['name']));
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) >= 3 && !in_array($w, $stopWords, true) && preg_match('/\b' . preg_quote($w, '/') . '\b/', $lower)) {
                return $hall;
            }
        }
    }
    // Generic "hall a" / "hall b" pattern mapped to nth hall in sort order.
    if (preg_match('/\bhall\s*([a-z])\b/i', $lower, $m)) {
        $idx = ord(strtolower($m[1])) - ord('a');
        if (isset($halls[$idx])) {
            return $halls[$idx];
        }
    }
    return null;
}

function wh_chatbot_extract_slot($text, $slots)
{
    $lower = strtolower($text);
    foreach ($slots as $slot) {
        $key = strtolower($slot['name']);
        $synonyms = WH_SLOT_SYNONYMS[$key] ?? [$key];
        foreach ($synonyms as $syn) {
            if (preg_match('/\b' . preg_quote($syn, '/') . '\b/', $lower)) {
                return $slot;
            }
        }
    }
    return null;
}

function wh_chatbot_contains_any($text, array $phrases)
{
    $lower = strtolower($text);
    foreach ($phrases as $p) {
        if (strpos($lower, $p) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Main entry point. Always returns ['answer' => string, 'intent' => string].
 * Every fact in the answer comes straight from a database query — nothing
 * is fabricated, and an unrecognized or empty query says so plainly.
 */
function wh_chatbot_answer($businessId, $question)
{
    $halls = wh_get_halls($businessId);
    $slots = wh_get_time_slots($businessId);

    $specificDate = wh_chatbot_extract_date($question);
    $month = $specificDate ? null : wh_chatbot_extract_month($question);
    $hall = wh_chatbot_extract_hall($question, $halls);
    $slot = wh_chatbot_extract_slot($question, $slots);

    $wantsPending = wh_chatbot_contains_any($question, ['pending payment', 'payment pending', 'baqi payment', 'baki payment', 'payment baqi', 'payment baki', 'outstanding payment', 'kitni payment']);
    $wantsNextBooking = wh_chatbot_contains_any($question, ['next booking', 'agli booking', 'aane wali booking', 'agla booking']);
    $wantsCount = wh_chatbot_contains_any($question, ['kitni booking', 'kitni bookings', 'how many booking', 'total booking', 'total bookings']);
    $wantsAdvance = wh_chatbot_contains_any($question, ['advance']);
    $wantsWhichHall = wh_chatbot_contains_any($question, ['konsa hall', 'kaunsa hall', 'kis hall', 'which hall']);
    $wantsEvents = wh_chatbot_contains_any($question, ['event', 'koi booking', 'booking hai', 'kya hai']);
    $wantsAvailability = wh_chatbot_contains_any($question, ['free', 'available', 'khali', 'khaali']);

    if ($wantsPending && !$wantsAdvance) {
        return wh_chatbot_intent_pending_payment($businessId, $hall);
    }
    if ($wantsNextBooking && $hall) {
        return wh_chatbot_intent_next_booking($businessId, $hall);
    }
    if ($wantsCount) {
        return wh_chatbot_intent_booking_count($businessId, $hall, $month);
    }
    if ($wantsAdvance) {
        return wh_chatbot_intent_advance_received($businessId, $month);
    }
    if ($specificDate) {
        if ($wantsWhichHall && $slot && !$hall) {
            return wh_chatbot_intent_slot_which_hall($businessId, $specificDate, $slot, $halls);
        }
        if ($wantsWhichHall && !$hall) {
            return wh_chatbot_intent_date_which_hall($businessId, $specificDate, $halls, $slots);
        }
        if ($hall) {
            return wh_chatbot_intent_hall_date_availability($businessId, $specificDate, $hall, $slot, $slots);
        }
        if ($wantsEvents || (!$wantsAvailability && !$wantsWhichHall)) {
            return wh_chatbot_intent_date_events($businessId, $specificDate);
        }
        return wh_chatbot_intent_date_which_hall($businessId, $specificDate, $halls, $slots);
    }
    if ($month) {
        return wh_chatbot_intent_booking_count($businessId, $hall, $month);
    }

    return [
        'intent' => 'unknown',
        'answer' => "Mujhe database mein is query ka record nahi mila. Aap date (e.g. 25 December), hall ka naam, ya month bata kar dobara poochh sakte hain.\n\nExamples:\n• \"25 Dec ko Hall A free hai?\"\n• \"Kal kon sa event hai?\"\n• \"Is month kitna advance receive hua?\"\n• \"Kitni payment pending hai?\"",
    ];
}

function wh_chatbot_intent_hall_date_availability($businessId, $date, $hall, $slot, $allSlots)
{
    $matrix = wh_availability_matrix($businessId, $date, $hall['id']);
    $slotsToShow = $slot ? [$slot] : $allSlots;
    $lines = [];
    foreach ($slotsToShow as $s) {
        $status = $matrix[$hall['id']][$s['id']] ?? 'available';
        $lines[] = '• ' . $s['name'] . ': ' . ($status === 'available' ? 'Available ✅' : 'Booked ❌');
    }
    $dateLabel = wh_format_date($date);
    $answer = $hall['name'] . ' — ' . $dateLabel . "\n" . implode("\n", $lines);
    return ['intent' => 'hall_date_availability', 'answer' => $answer];
}

function wh_chatbot_intent_date_which_hall($businessId, $date, $halls, $slots)
{
    $matrix = wh_availability_matrix($businessId, $date, null);
    $lines = [];
    foreach ($halls as $hall) {
        $availableSlots = [];
        foreach ($slots as $s) {
            if (($matrix[$hall['id']][$s['id']] ?? 'available') === 'available') {
                $availableSlots[] = $s['name'];
            }
        }
        $lines[] = '• ' . $hall['name'] . ': ' . ($availableSlots ? implode(', ', $availableSlots) . ' available' : 'Fully booked');
    }
    $answer = wh_format_date($date) . " ko availability:\n" . implode("\n", $lines);
    return ['intent' => 'date_which_hall', 'answer' => $answer];
}

function wh_chatbot_intent_slot_which_hall($businessId, $date, $slot, $halls)
{
    $booked = wh_fetch_all(
        "SELECT h.name FROM slot_locks sl JOIN halls h ON h.id = sl.hall_id
         WHERE sl.business_id = ? AND sl.booking_date = ? AND sl.time_slot_id = ?",
        'isi',
        [$businessId, $date, $slot['id']]
    );
    $bookedNames = array_column($booked, 'name');
    $available = array_diff(array_column($halls, 'name'), $bookedNames);

    if ($bookedNames) {
        $answer = wh_format_date($date) . ' ki ' . $slot['name'] . ' booking: ' . implode(', ', $bookedNames) . " mein hai.\n";
    } else {
        $answer = wh_format_date($date) . ' ki ' . $slot['name'] . " slot mein koi booking nahi hai.\n";
    }
    $answer .= 'Available in this slot: ' . ($available ? implode(', ', $available) : 'None — fully booked');
    return ['intent' => 'slot_which_hall', 'answer' => $answer];
}

function wh_chatbot_intent_date_events($businessId, $date)
{
    $rows = wh_fetch_all(
        "SELECT b.*, h.name AS hall_name, ts.name AS slot_name, et.name AS event_type_name, c.name AS customer_name
         FROM bookings b JOIN halls h ON h.id=b.hall_id JOIN time_slots ts ON ts.id=b.time_slot_id
         LEFT JOIN event_types et ON et.id=b.event_type_id JOIN customers c ON c.id=b.customer_id
         WHERE b.business_id=? AND b.booking_date=? AND b.booking_status != 'cancelled' ORDER BY ts.sort_order",
        'is',
        [$businessId, $date]
    );
    if (!$rows) {
        return ['intent' => 'date_events', 'answer' => wh_format_date($date) . ' ko koi booking nahi hai. Sabhi halls available hain.'];
    }
    $lines = [];
    foreach ($rows as $r) {
        $lines[] = '• ' . $r['hall_name'] . ' (' . $r['slot_name'] . '): ' . ($r['event_type_name'] ?? 'Event') . ' — ' . $r['customer_name'] . ', ' . (int) $r['guests'] . ' guests [' . ucfirst($r['booking_status']) . ']';
    }
    return ['intent' => 'date_events', 'answer' => wh_format_date($date) . " ki bookings:\n" . implode("\n", $lines)];
}

function wh_chatbot_intent_booking_count($businessId, $hall, $month)
{
    $where = ["business_id = ?", "booking_status != 'cancelled'"];
    $types = 'i';
    $params = [$businessId];
    $label = 'Total';

    if ($month) {
        $start = sprintf('%04d-%02d-01', $month['year'], $month['month']);
        $end = date('Y-m-t', strtotime($start));
        $where[] = 'booking_date BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = $start; $params[] = $end;
        $label = date('F Y', strtotime($start));
    }
    if ($hall) {
        $where[] = 'hall_id = ?';
        $types .= 'i';
        $params[] = $hall['id'];
    }

    $row = wh_fetch_one('SELECT COUNT(*) c FROM bookings WHERE ' . implode(' AND ', $where), $types, $params);
    $count = (int) ($row['c'] ?? 0);

    $subject = $hall ? $hall['name'] : 'Sabhi halls';
    $answer = $subject . ' mein ' . $label . ' mein ' . $count . ' booking' . ($count === 1 ? '' : 's') . ' hain.';
    return ['intent' => 'booking_count', 'answer' => $answer];
}

function wh_chatbot_intent_advance_received($businessId, $month)
{
    if (!$month) {
        $today = new DateTime('today');
        $month = ['year' => (int) $today->format('Y'), 'month' => (int) $today->format('n')];
    }
    $start = sprintf('%04d-%02d-01', $month['year'], $month['month']);
    $end = date('Y-m-t', strtotime($start));
    $row = wh_fetch_one(
        'SELECT COALESCE(SUM(amount),0) t FROM booking_payments WHERE business_id=? AND payment_date BETWEEN ? AND ?',
        'iss',
        [$businessId, $start, $end]
    );
    $answer = date('F Y', strtotime($start)) . ' mein ' . wh_format_money($row['t'] ?? 0) . ' advance/payment receive hua hai.';
    return ['intent' => 'advance_received', 'answer' => $answer];
}

function wh_chatbot_intent_pending_payment($businessId, $hall)
{
    $where = ["business_id = ?", "booking_status != 'cancelled'", 'balance > 0'];
    $types = 'i';
    $params = [$businessId];
    if ($hall) {
        $where[] = 'hall_id = ?';
        $types .= 'i';
        $params[] = $hall['id'];
    }
    $row = wh_fetch_one('SELECT COALESCE(SUM(balance),0) t, COUNT(*) n FROM bookings WHERE ' . implode(' AND ', $where), $types, $params);
    $subject = $hall ? $hall['name'] : 'Total';
    $answer = $subject . ' mein ' . wh_format_money($row['t'] ?? 0) . ' payment pending hai (' . (int) ($row['n'] ?? 0) . ' bookings mein).';
    return ['intent' => 'pending_payment', 'answer' => $answer];
}

function wh_chatbot_intent_next_booking($businessId, $hall)
{
    $row = wh_fetch_one(
        "SELECT b.booking_date, ts.name AS slot_name, c.name AS customer_name, et.name AS event_type_name
         FROM bookings b JOIN time_slots ts ON ts.id=b.time_slot_id JOIN customers c ON c.id=b.customer_id
         LEFT JOIN event_types et ON et.id=b.event_type_id
         WHERE b.business_id=? AND b.hall_id=? AND b.booking_date >= CURDATE() AND b.booking_status IN ('pending','confirmed','hold')
         ORDER BY b.booking_date ASC LIMIT 1",
        'ii',
        [$businessId, $hall['id']]
    );
    if (!$row) {
        return ['intent' => 'next_booking', 'answer' => $hall['name'] . ' ki koi upcoming booking nahi hai — yeh hall abhi khali hai.'];
    }
    $answer = $hall['name'] . ' ki next booking: ' . wh_format_date($row['booking_date']) . ' (' . $row['slot_name'] . ') — ' . ($row['event_type_name'] ?? 'Event') . ', ' . $row['customer_name'] . '.';
    return ['intent' => 'next_booking', 'answer' => $answer];
}
