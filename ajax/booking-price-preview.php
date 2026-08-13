<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$serviceId = (int) ($_GET['service_id'] ?? 0);
$service = $serviceId ? db_select_one($conn, 'SELECT * FROM services WHERE id = ? AND status = "approved"', [$serviceId]) : null;
if (!$service) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$units = booking_units_from_request($service, $_GET);
if ($units === null) {
    echo json_encode(['ok' => false, 'error' => 'Fill in the required dates.']);
    exit;
}

$breakdown = calculate_booking_price($conn, $service, $units);
$breakdown['ok'] = true;
$breakdown['currency_symbol'] = get_setting($conn, 'currency_symbol', '$');
echo json_encode($breakdown);
