<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$bookingMode = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT booking_mode FROM doctors WHERE id = ' . (int) $doctorId))['booking_mode'] ?? 'slots';

$availByDay = get_doctor_availability_by_day($doctorId);
$ticketScheduleByDay = get_doctor_ticket_schedule($doctorId);

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date >= CURDATE() ORDER BY blocked_date');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$blockedDates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'Availability';
$heading = 'Availability';
$extraScripts = '<script defer src="' . asset_url('/assets/js/doctor-availability.js') . '"></script>';
if ($bookingMode === 'tickets') {
    $extraScripts .= '<script defer src="' . asset_url('/assets/js/doctor-ticket-schedule.js') . '"></script>';
}
require __DIR__ . '/includes/header.php';
?>
<?php if ($bookingMode === 'tickets'): ?>
<div class="card" style="padding:28px;margin-bottom:24px;" data-reveal>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h4>Ticket Queue Hours</h4>
            <p style="color:var(--color-text-muted);font-size:13px;margin-top:2px;">You're set to <strong>ticket / token queue</strong> mode (an admin controls this). Set which days and hours your queue runs — patients booking for one of these days get a number, first-come-first-served; you call them out in order from the <a href="/doctor/queue">Ticket Queue</a> page.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="save-ticket-schedule-btn" style="flex-shrink:0;">Save Hours</button>
    </div>
    <div class="grid grid-2" id="ticket-schedule-days" style="gap:12px;">
        <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $dayIndex => $dayName): $row = $ticketScheduleByDay[$dayIndex] ?? null; ?>
        <div class="card" style="padding:16px;" data-day="<?= $dayIndex ?>">
            <label class="checkbox-row" style="margin-bottom:10px;font-weight:600;">
                <input type="checkbox" class="ticket-day-enabled" <?= $row ? 'checked' : '' ?>> <?= e($dayName) ?>
            </label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="time" class="form-control ticket-day-start" value="<?= e($row['start_time'] ?? '09:00') ?>" style="flex:1;">
                <span style="color:var(--color-text-muted);">to</span>
                <input type="time" class="form-control ticket-day-end" value="<?= e($row['end_time'] ?? '17:00') ?>" style="flex:1;">
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding:28px;margin-bottom:24px;" data-reveal>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h4>Weekly Schedule</h4>
            <p style="color:var(--color-text-muted);font-size:13px;margin-top:2px;">Set separate hours for online video consultations and your physical clinic/hospital — enable only the ones that apply on each day.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="save-availability-btn" style="flex-shrink:0;">Save Schedule</button>
    </div>
    <?php require __DIR__ . '/../includes/availability-form-fields.php'; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:28px;" data-reveal>
    <h4 style="margin-bottom:20px;">Blocked Dates (Holidays / Vacation)</h4>
    <form id="block-date-form" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="date" name="date" class="form-control" style="max-width:180px;" min="<?= date('Y-m-d') ?>" required>
        <input type="text" name="reason" class="form-control" placeholder="Reason (optional)" style="flex:1;min-width:180px;">
        <button type="submit" class="btn btn-outline">Block Date</button>
    </form>
    <div id="blocked-dates-list" class="stagger">
        <?php if (!$blockedDates): ?>
        <p style="color:var(--color-text-muted);" id="no-blocked-msg">No blocked dates yet.</p>
        <?php else: foreach ($blockedDates as $b): ?>
        <div class="card" style="padding:14px 18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;" data-block-id="<?= (int)$b['id'] ?>">
            <div><strong><?= format_date($b['blocked_date']) ?></strong><?php if ($b['reason']): ?> — <span style="color:var(--color-text-muted);"><?= e($b['reason']) ?></span><?php endif; ?></div>
            <button type="button" class="btn-icon btn-remove-block" style="width:32px;height:32px;"><i class="ri-close-line"></i></button>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
