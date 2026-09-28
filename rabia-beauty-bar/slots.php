<?php
/** JSON endpoint: available booking slots for ?date=YYYY-MM-DD */
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$date = (string) ($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    echo json_encode(['slots' => [], 'message' => 'Please pick a valid date.']);
    exit;
}
$closed = setting('closed_day', '');
if ($closed !== '' && date('l', strtotime($date)) === $closed) {
    echo json_encode(['slots' => [], 'message' => "We're closed on {$closed}s — please choose another day."]);
    exit;
}
$slots = array_map(fn ($s) => ['value' => $s, 'label' => slot_label($s)], available_slots($date));
echo json_encode(['slots' => $slots]);
