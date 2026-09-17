<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

$businessId = wh_current_business_id();
$date = wh_input_get('date');
$hallId = (int) wh_input_get('hall_id', 0);

if (!$date || !strtotime($date)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid date.']);
    exit;
}

$isAdmin = wh_is_logged_in();
$publicVisible = $isAdmin || wh_setting_bool('show_public_availability', true, $businessId);

if (!$publicVisible) {
    echo json_encode(['success' => true, 'public_visible' => false]);
    exit;
}

if ($hallId) {
    $hall = wh_get_hall($hallId, $businessId);
    if ($hall && !$isAdmin && (!$hall['is_public'] || $hall['status'] !== 'active')) {
        $hall = null;
    }
    $halls = $hall ? [$hall] : [];
} else {
    $halls = wh_get_halls($businessId, !$isAdmin);
}
$slots = wh_get_time_slots($businessId);
$matrix = wh_availability_matrix($businessId, $date, $hallId ?: null);

$out = [];
foreach ($halls as $hall) {
    $slotOut = [];
    foreach ($slots as $slot) {
        $slotOut[] = [
            'id' => (int) $slot['id'],
            'name' => $slot['name'],
            'time_range' => wh_format_time($slot['start_time']) . '–' . wh_format_time($slot['end_time']),
            'status' => $matrix[$hall['id']][$slot['id']] ?? 'available',
        ];
    }
    $out[] = ['id' => (int) $hall['id'], 'name' => $hall['name'], 'slots' => $slotOut];
}

echo json_encode(['success' => true, 'public_visible' => true, 'date' => $date, 'halls' => $out]);
