<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');
wh_require_admin();

$businessId = wh_current_business_id();
if (wh_input_get('action') === 'mark_read') {
    wh_mark_notifications_read($businessId);
}
echo json_encode(['success' => true]);
