<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');
wh_require_admin();

$businessId = wh_current_business_id();
$year = (int) wh_input_get('year', date('Y'));
$month = (int) wh_input_get('month', date('n'));
$hallId = (int) wh_input_get('hall_id', 0);

$summary = wh_month_calendar_summary($businessId, $year, $month, $hallId ?: null);
echo json_encode(['success' => true, 'year' => $year, 'month' => $month, 'days' => $summary]);
