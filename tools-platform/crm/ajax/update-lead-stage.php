<?php
require __DIR__ . '/../../includes/config.php';
require_business();
header('Content-Type: application/json');

if (!tp_csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Session expired, please refresh.']);
    exit;
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$businessId = tp_current_business_id();

if (!isset(CRM_PIPELINE_STAGES[$status]) || $leadId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid stage.']);
    exit;
}

$result = tp_execute(
    'UPDATE crm_leads SET status = ? WHERE id = ? AND business_id = ?',
    'sii',
    [$status, $leadId, $businessId]
);

echo json_encode(['success' => $result['success']]);
