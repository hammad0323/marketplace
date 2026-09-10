<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$search = clean($_GET['q'] ?? '');
$where = '1=1';
$params = [];
$types = '';
if ($search !== '') {
    $where = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like];
    $types = 'ss';
}

$sql = "SELECT p.*, u.full_name, u.email, u.phone, u.avatar, u.status, u.created_at,
    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS total_appointments
    FROM patients p JOIN users u ON u.id = p.user_id WHERE $where ORDER BY u.created_at DESC";
$stmt = mysqli_prepare(db(), $sql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$patients = mysqli_stmt_get_result($stmt);

$pageTitle = 'Patients';
$heading = 'Manage Patients';
$extraScripts = '<script defer src="/assets/js/admin-patients.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<form method="get" style="margin-bottom:20px;max-width:360px;">
    <div class="input-icon-wrap">
        <i class="ri-search-line"></i>
        <input type="text" name="q" class="form-control" placeholder="Search by name or email" value="<?= e($search) ?>">
    </div>
</form>

<?php if (mysqli_num_rows($patients) === 0): ?>
<div class="empty-state card"><i class="ri-group-line"></i><h4>No patients found</h4></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Patient</th><th>Contact</th><th>Appointments</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($patients)): ?>
        <tr data-user-id="<?= (int)$p['user_id'] ?>">
            <td class="table-user"><img src="<?= e(avatar_url($p['avatar'], $p['full_name'])) ?>"><?= e($p['full_name']) ?></td>
            <td><?= e($p['email']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($p['phone']) ?></span></td>
            <td><?= (int)$p['total_appointments'] ?></td>
            <td><?= format_date($p['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
            <td>
                <?php if ($p['status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm btn-user-action" data-action="suspend">Suspend</button>
                <?php else: ?>
                <button class="btn btn-primary btn-sm btn-user-action" data-action="activate">Activate</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
