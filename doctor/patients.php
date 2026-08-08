<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$patients = mysqli_query(db(), "
    SELECT u.id, p.id AS patient_id, u.full_name, u.avatar, u.email, u.phone,
        COUNT(a.id) AS total_appointments,
        MAX(a.appointment_date) AS last_visit
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE a.doctor_id = $doctorId
    GROUP BY u.id, p.id, u.full_name, u.avatar, u.email, u.phone
    ORDER BY last_visit DESC
");

$pageTitle = 'My Patients';
$heading = 'My Patients';
require __DIR__ . '/includes/header.php';
?>
<div class="card table-card" data-reveal>
    <?php if (mysqli_num_rows($patients) === 0): ?>
    <div class="empty-state"><i class="ri-group-line"></i><h4>No patients yet</h4><p>Patients who book with you will show up here.</p></div>
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
            <td><a href="/doctor/messages?patient_id=<?= (int) $p['patient_id'] ?>" class="btn btn-outline btn-sm"><i class="ri-chat-3-line"></i> Message</a></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
