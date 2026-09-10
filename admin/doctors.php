<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$filter = $_GET['status'] ?? 'pending';
$statusMap = ['pending' => "d.verification_status = 'pending'", 'verified' => "d.verification_status = 'verified'", 'rejected' => "d.verification_status = 'rejected'", 'all' => '1=1'];
$condition = $statusMap[$filter] ?? $statusMap['pending'];

$doctors = mysqli_query(db(), "
    SELECT d.*, u.full_name, u.avatar, u.email, u.phone, u.status AS user_status,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS spec_names
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE $condition ORDER BY d.created_at DESC
");

$pageTitle = 'Doctors';
$heading = 'Manage Doctors';
$extraScripts = '<script defer src="/assets/js/admin-doctors.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <a href="?status=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?status=verified" class="tab-btn <?= $filter === 'verified' ? 'active' : '' ?>">Verified</a>
    <a href="?status=rejected" class="tab-btn <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
    <a href="?status=all" class="tab-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
</div>

<?php if (mysqli_num_rows($doctors) === 0): ?>
<div class="empty-state card"><i class="ri-stethoscope-line"></i><h4>No doctors here</h4><p>Nothing to show in this tab.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Doctor</th><th>Specialization</th><th>License #</th><th>Applied</th><th>Status</th><th>Account</th><th>Actions</th></tr></thead>
        <tbody>
        <?php while ($d = mysqli_fetch_assoc($doctors)): ?>
        <tr data-doctor-id="<?= (int)$d['id'] ?>" data-meta-title="<?= e($d['meta_title']) ?>" data-meta-description="<?= e($d['meta_description']) ?>">
            <td class="table-user" title="<?= e($d['qualification'] . ' · ' . $d['experience_years'] . ' yrs · ' . excerpt($d['bio'], 160)) ?>">
                <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>">
                <div><?= e($d['full_name']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($d['email']) ?></span></div>
            </td>
            <td><?= e($d['spec_names']) ?></td>
            <td><?= e($d['registration_number']) ?></td>
            <td><?= format_date($d['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($d['verification_status']) ?>"><?= ucfirst($d['verification_status']) ?></span>
                <?php if ($d['is_premium']): ?><span class="badge badge-premium" style="margin-left:4px;">Premium</span><?php endif; ?>
            </td>
            <td><span class="status-pill status-<?= e($d['user_status']) ?>"><?= ucfirst($d['user_status']) ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($d['verification_status'] === 'pending'): ?>
                <button class="btn btn-primary btn-sm btn-doc-action" data-action="verify">Approve</button>
                <button class="btn btn-danger btn-sm btn-doc-action" data-action="reject">Reject</button>
                <?php else: ?>
                <button class="btn btn-outline btn-sm btn-doc-action" data-action="toggle_premium"><?= $d['is_premium'] ? 'Unset Premium' : 'Make Premium' ?></button>
                <?php if ($d['user_status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm btn-doc-action" data-action="suspend">Suspend</button>
                <?php else: ?>
                <button class="btn btn-primary btn-sm btn-doc-action" data-action="activate">Activate</button>
                <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm btn-doc-seo" title="Edit SEO meta title/description"><i class="ri-search-eye-line"></i> SEO</button>
                <a href="<?= e(doctor_url($d['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
