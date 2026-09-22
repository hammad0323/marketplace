<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$bookingMode = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT booking_mode FROM doctors WHERE id = ' . (int) $doctorId))['booking_mode'] ?? 'slots';
if ($bookingMode !== 'tickets') {
    redirect('/doctor/dashboard');
}

$pageTitle = 'Ticket Queue';
$heading = 'Ticket Queue';
$extraScripts = '<script defer src="' . asset_url('/assets/js/ticket-queue.js') . '"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <p style="color:var(--color-text-muted);margin:0;">Call patients in order, or add a walk-in who came straight to the clinic. <a href="/doctor/ticket-report">View reports</a> · <a href="/doctor/managers">Manage staff access</a></p>
</div>
<?php require __DIR__ . '/../includes/ticket-queue-panel.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
