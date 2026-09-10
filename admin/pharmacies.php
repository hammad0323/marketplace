<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$filter = $_GET['status'] ?? 'pending';
$statusMap = ['pending' => "p.verification_status = 'pending'", 'verified' => "p.verification_status = 'verified'", 'rejected' => "p.verification_status = 'rejected'", 'all' => '1=1'];
$condition = $statusMap[$filter] ?? $statusMap['pending'];

$pharmacies = mysqli_query(db(), "
    SELECT p.*, u.full_name, u.avatar, u.email, u.phone, u.status AS user_status,
        (SELECT GROUP_CONCAT(pc.file_path SEPARATOR '|') FROM pharmacy_certificates pc WHERE pc.pharmacy_id = p.id) AS cert_files
    FROM pharmacies p JOIN users u ON u.id = p.user_id
    WHERE $condition ORDER BY p.created_at DESC
");

$pageTitle = 'Pharmacies';
$heading = 'Manage Pharmacies';
$extraScripts = '<script defer src="/assets/js/admin-pharmacies.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <a href="?status=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?status=verified" class="tab-btn <?= $filter === 'verified' ? 'active' : '' ?>">Verified</a>
    <a href="?status=rejected" class="tab-btn <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
    <a href="?status=all" class="tab-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
</div>

<?php if (mysqli_num_rows($pharmacies) === 0): ?>
<div class="empty-state card"><i class="ri-capsule-line"></i><h4>No pharmacies here</h4><p>Nothing to show in this tab.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Store</th><th>Owner / Contact</th><th>License #</th><th>Certificates</th><th>Applied</th><th>Status</th><th>Account</th><th>Actions</th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($pharmacies)): ?>
        <tr data-pharmacy-id="<?= (int) $p['id'] ?>">
            <td class="table-user" title="<?= e($p['bio']) ?>">
                <img src="<?= e(avatar_url($p['avatar'], $p['store_name'])) ?>">
                <div><?= e($p['store_name']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($p['city']) ?></span></div>
            </td>
            <td><?= e($p['full_name']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($p['email']) ?></span></td>
            <td><?= e($p['registration_number']) ?><?php if ($p['license_authority']): ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($p['license_authority']) ?></span><?php endif; ?></td>
            <td>
                <?php if ($p['cert_files']): $files = explode('|', $p['cert_files']); ?>
                <?php foreach ($files as $i => $f): ?><a href="/uploads/<?= e($f) ?>" target="_blank" style="display:block;">Certificate <?= $i + 1 ?></a><?php endforeach; ?>
                <?php else: ?>
                <span style="color:var(--color-text-muted);">None</span>
                <?php endif; ?>
            </td>
            <td><?= format_date($p['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($p['verification_status']) ?>"><?= ucfirst($p['verification_status']) ?></span></td>
            <td><span class="status-pill status-<?= e($p['user_status']) ?>"><?= ucfirst($p['user_status']) ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($p['verification_status'] === 'pending'): ?>
                <button class="btn btn-primary btn-sm btn-pharm-action" data-action="verify">Approve</button>
                <button class="btn btn-danger btn-sm btn-pharm-action" data-action="reject">Reject</button>
                <?php else: ?>
                <?php if ($p['user_status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm btn-pharm-action" data-action="suspend">Suspend</button>
                <?php else: ?>
                <button class="btn btn-primary btn-sm btn-pharm-action" data-action="activate">Activate</button>
                <?php endif; ?>
                <a href="<?= e(pharmacy_url($p['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
