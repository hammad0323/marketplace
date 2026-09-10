<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();

$existing = [];
$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY day_of_week');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $existing[(int) $row['day_of_week']][] = $row;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date >= CURDATE() ORDER BY blocked_date');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$blockedDates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'Availability';
$heading = 'Availability';
$extraScripts = '<script defer src="/assets/js/doctor-availability.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="padding:28px;margin-bottom:24px;" data-reveal>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h4>Weekly Schedule</h4>
        <button type="button" class="btn btn-primary btn-sm" id="save-availability-btn">Save Schedule</button>
    </div>
    <div style="overflow-x:auto;">
    <div class="table-scroll"><table class="data-table" id="availability-table">
        <thead><tr><th>Day</th><th>Enabled</th><th>Start</th><th>End</th><th>Slot Length</th><th>Type</th></tr></thead>
        <tbody>
        <?php for ($d = 0; $d <= 6; $d++): $row = $existing[$d][0] ?? null; ?>
        <tr data-day="<?= $d ?>">
            <td style="font-weight:600;"><?= day_name($d) ?></td>
            <td><input type="checkbox" class="day-enabled" <?= $row ? 'checked' : '' ?>></td>
            <td><input type="time" class="form-control day-start" value="<?= $row ? substr($row['start_time'], 0, 5) : '09:00' ?>" style="width:120px;"></td>
            <td><input type="time" class="form-control day-end" value="<?= $row ? substr($row['end_time'], 0, 5) : '17:00' ?>" style="width:120px;"></td>
            <td>
                <select class="form-control day-duration" style="width:110px;">
                    <?php foreach ([15, 20, 30, 45, 60] as $mins): ?>
                    <option value="<?= $mins ?>" <?= ($row['slot_duration_mins'] ?? 30) == $mins ? 'selected' : '' ?>><?= $mins ?> min</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select class="form-control day-type" style="width:130px;">
                    <?php foreach (['both' => 'Online + In-Person', 'online' => 'Online only', 'physical' => 'In-Person only'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($row['consultation_type'] ?? 'both') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php endfor; ?>
        </tbody>
    </table></div>
    </div>
</div>

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
