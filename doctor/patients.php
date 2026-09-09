<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$q = clean($_GET['q'] ?? '');

$where = ['a.doctor_id = ?'];
$params = [$doctorId];
$types = 'i';
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
$whereSql = implode(' AND ', $where);

$stmt = mysqli_prepare(db(), "
    SELECT u.id, p.id AS patient_id, u.full_name, u.avatar, u.email, u.phone,
        COUNT(a.id) AS total_appointments,
        MAX(a.appointment_date) AS last_visit,
        (SELECT COUNT(*) FROM patient_medical_history h WHERE h.patient_id = p.id AND h.doctor_id = a.doctor_id) AS history_count
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE $whereSql
    GROUP BY u.id, p.id, u.full_name, u.avatar, u.email, u.phone
    ORDER BY last_visit DESC
");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$patients = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$pageTitle = 'My Patients';
$heading = 'My Patients';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="padding:16px 20px;margin-bottom:20px;" data-reveal>
    <form method="get" style="display:flex;gap:10px;">
        <input type="text" name="q" class="form-control" placeholder="Search by name, phone, or email…" value="<?= e($q) ?>" style="max-width:360px;">
        <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> Search</button>
        <?php if ($q !== ''): ?><a href="/doctor/patients" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
</div>
<div class="card table-card" data-reveal>
    <?php if (mysqli_num_rows($patients) === 0): ?>
    <div class="empty-state"><i class="ri-group-line"></i><h4>No patients found</h4><p><?= $q !== '' ? 'No match for your search.' : 'Patients who book with you will show up here.' ?></p></div>
    <?php else: ?>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Patient</th><th>Contact</th><th>Total Visits</th><th>Last Visit</th><th></th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($patients)): ?>
        <tr>
            <td class="table-user"><img src="<?= e(avatar_url($p['avatar'], $p['full_name'])) ?>"><?= e($p['full_name']) ?></td>
            <td><?= e($p['email']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($p['phone']) ?></span></td>
            <td><?= (int)$p['total_appointments'] ?></td>
            <td><?= format_date($p['last_visit']) ?></td>
            <td style="white-space:nowrap;">
                <a href="/doctor/patient-history?patient_id=<?= (int) $p['patient_id'] ?>" class="btn btn-outline btn-sm"><i class="ri-file-list-3-line"></i> History<?= $p['history_count'] > 0 ? ' (' . (int) $p['history_count'] . ')' : '' ?></a>
                <a href="/doctor/messages?patient_id=<?= (int) $p['patient_id'] ?>" class="btn btn-outline btn-sm"><i class="ri-chat-3-line"></i> Message</a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
