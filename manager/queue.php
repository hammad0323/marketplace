<?php
require __DIR__ . '/../config/config.php';
require_role_page('manager');

$doctorId = current_manager_doctor_id();
if (!$doctorId) {
    http_response_code(403);
    exit('Your manager account is not linked to a doctor.');
}

$pageTitle = 'Ticket Queue';
$heading = 'Ticket Queue';
$extraScripts = '<script defer src="' . asset_url('/assets/js/ticket-queue.js') . '"></script>';
require __DIR__ . '/includes/header.php';
?>
<?php require __DIR__ . '/../includes/ticket-queue-panel.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
