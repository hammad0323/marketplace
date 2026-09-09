<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$patientId = (int) ($_GET['patient_id'] ?? 0);

$stmt = mysqli_prepare(db(), "
    SELECT p.id, u.full_name, u.avatar, u.email, u.phone
    FROM patients p JOIN users u ON u.id = p.user_id
    WHERE p.id = ? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = ?)
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ii', $patientId, $doctorId);
mysqli_stmt_execute($stmt);
$patient = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$patient) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$stmt = mysqli_prepare(db(), 'SELECT * FROM patient_medical_history WHERE doctor_id = ? AND patient_id = ? ORDER BY visit_date DESC, created_at DESC');
mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $patientId);
mysqli_stmt_execute($stmt);
$history = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$statusLabels = ['ongoing' => 'Ongoing', 'improving' => 'Improving', 'stable' => 'Stable', 'recovered' => 'Recovered / Okay', 'critical' => 'Critical'];

$pageTitle = 'Patient History — ' . $patient['full_name'];
$heading = 'Patient History';
$extraScripts = '<script src="/assets/js/patient-history.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<a href="/doctor/patients" style="display:inline-flex;align-items:center;gap:6px;font-size:13.5px;color:var(--color-text-muted);margin-bottom:16px;"><i class="ri-arrow-left-line"></i> Back to My Patients</a>

<div class="card" style="padding:22px;margin-bottom:24px;display:flex;gap:16px;align-items:center;" data-reveal>
    <img src="<?= e(avatar_url($patient['avatar'], $patient['full_name'])) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
    <div>
        <h3 style="margin-bottom:4px;"><?= e($patient['full_name']) ?></h3>
        <p style="font-size:13px;color:var(--color-text-muted);"><?= e($patient['email']) ?> · <?= e($patient['phone'] ?: 'No phone on file') ?></p>
    </div>
</div>

<div class="card" style="padding:24px;margin-bottom:24px;" data-reveal>
    <h4 style="margin-bottom:16px;">Add History Entry</h4>
    <form id="history-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
        <div class="grid grid-2">
            <div class="form-group" data-field="title"><label class="form-label">Title</label><input type="text" class="form-control" name="title" placeholder="e.g. Follow-up consultation" required><div class="form-error"></div></div>
            <div class="form-group"><label class="form-label">Visit Date</label><input type="date" class="form-control" name="visit_date" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" placeholder="How the patient was treated, findings, notes…"></textarea></div>
        <div class="form-group" style="max-width:260px;">
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
                <?php foreach ($statusLabels as $val => $label): ?>
                <option value="<?= $val ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Add Entry</button>
    </form>
</div>

<h4 style="margin-bottom:16px;">History (<?= count($history) ?>)</h4>
<div id="history-list">
    <?php if (!$history): ?>
    <div class="empty-state card" data-reveal><i class="ri-file-list-3-line"></i><h4>No history yet</h4><p>Entries you add above will show up here.</p></div>
    <?php else: foreach ($history as $h): ?>
    <div class="card" style="padding:20px;margin-bottom:14px;" data-reveal data-history-id="<?= (int) $h['id'] ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;">
            <div>
                <strong style="display:block;"><?= e($h['title']) ?></strong>
                <span style="font-size:12.5px;color:var(--color-text-muted);"><?= $h['visit_date'] ? format_date($h['visit_date']) : format_date($h['created_at']) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="status-pill status-<?= e($h['status']) ?>"><?= e($statusLabels[$h['status']] ?? ucfirst($h['status'])) ?></span>
                <button type="button" class="btn-icon btn-delete-history" style="width:30px;height:30px;"><i class="ri-delete-bin-line"></i></button>
            </div>
        </div>
        <?php if ($h['description']): ?><p style="font-size:14px;white-space:pre-line;"><?= e($h['description']) ?></p><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
