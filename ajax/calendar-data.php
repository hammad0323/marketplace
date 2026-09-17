<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

$businessId = wh_current_business_id();
$isAdmin = wh_is_logged_in();
$publicVisible = $isAdmin || wh_setting_bool('show_public_availability', true, $businessId);

if (!$publicVisible) {
    echo json_encode(['success' => true, 'public_visible' => false, 'days' => []]);
    exit;
}

$year = (int) wh_input_get('year', date('Y'));
$month = (int) wh_input_get('month', date('n'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$hallId = (int) wh_input_get('hall_id', 0);
if ($hallId && !$isAdmin) {
    $hall = wh_get_hall($hallId, $businessId);
    if (!$hall || !$hall['is_public'] || $hall['status'] !== 'active') {
        $hallId = 0;
    }
}

$summary = wh_month_calendar_summary($businessId, $year, $month, $hallId ?: null);
echo json_encode(['success' => true, 'public_visible' => true, 'year' => $year, 'month' => $month, 'days' => $summary]);
