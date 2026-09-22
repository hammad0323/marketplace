<?php
/**
 * Shared ticket-queue management UI — required by both doctor/queue.php and
 * manager/queue.php so the doctor and their front-desk manager see and use
 * the exact same live queue. Expects $doctorId (the doctor whose queue this
 * is) already resolved and validated by the caller.
 */
$queueDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $queueDate)) {
    $queueDate = date('Y-m-d');
}

$counter = mysqli_fetch_assoc(mysqli_query(db(), '
    SELECT last_number, current_serving FROM doctor_ticket_counters
    WHERE doctor_id = ' . (int) $doctorId . " AND ticket_date = '" . mysqli_real_escape_string(db(), $queueDate) . "' LIMIT 1
")) ?: ['last_number' => 0, 'current_serving' => 0];

$doctorFullName = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT u.full_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ' . (int) $doctorId))['full_name'] ?? '';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <label class="form-label" style="margin-bottom:0;">Date</label>
        <input type="date" class="form-control" id="queue-date-input" value="<?= e($queueDate) ?>" style="max-width:180px;">
    </div>
    <button type="button" class="btn btn-outline btn-sm" id="add-walkin-btn"><i class="ri-user-add-line"></i> Add Walk-in Ticket</button>
</div>

<div class="grid grid-2" style="gap:20px;margin-bottom:24px;">
    <div class="card" style="padding:28px;text-align:center;" data-reveal>
        <div style="font-size:12.5px;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:8px;">Now Serving</div>
        <div style="font-size:56px;font-weight:800;color:var(--color-primary);" id="now-serving-number"><?= (int) $counter['current_serving'] ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:18px;" id="now-serving-name">&nbsp;</div>
        <button type="button" class="btn btn-primary btn-block" id="call-next-btn"><i class="ri-skip-forward-fill"></i> Call Next</button>
    </div>
    <div class="card" style="padding:28px;text-align:center;" data-reveal>
        <div style="font-size:12.5px;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:8px;">Total Tickets Today</div>
        <div style="font-size:56px;font-weight:800;" id="last-number-display"><?= (int) $counter['last_number'] ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">for <?= e(format_date($queueDate)) ?></div>
        <p style="font-size:12px;color:var(--color-text-muted);margin-top:14px;">Share this link so patients can check the current number without calling: <br><a href="<?= e(APP_URL . '/queue-status?doctor_id=' . (int) $doctorId) ?>" target="_blank" style="color:var(--color-primary);word-break:break-all;"><?= e(APP_URL . '/queue-status?doctor_id=' . (int) $doctorId) ?></a></p>
    </div>
</div>

<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>#</th><th>Patient</th><th>Phone</th><th>Status</th><th>Added By</th><th>Actions</th></tr></thead>
        <tbody id="ticket-queue-tbody">
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px;">Loading…</td></tr>
        </tbody>
    </table></div>
</div>

<div class="modal-overlay" id="walkin-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:420px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:32px;">
            <h3 style="margin-bottom:18px;">Add Walk-in Ticket</h3>
            <form id="walkin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-group" data-field="name">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control" name="name" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone (optional)</label>
                    <input type="tel" class="form-control" name="phone">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Add to Queue</button>
            </form>
        </div>
    </div>
</div>
<script>window.TICKET_QUEUE_DOCTOR_ID = <?= (int) $doctorId ?>;</script>
