<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$total = (int) mysqli_fetch_assoc(mysqli_query(db(), 'SELECT COUNT(*) c FROM activity_logs'))['c'];
$pagination = paginate($total, 25);

$logs = mysqli_query(db(), "
    SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");

$pageTitle = 'Activity Logs';
$heading = 'Activity Logs';
require __DIR__ . '/includes/header.php';
?>
<div class="card table-card" data-reveal>
    <table class="data-table">
        <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
        <?php while ($l = mysqli_fetch_assoc($logs)): ?>
        <tr>
            <td><?= e($l['full_name'] ?: 'Guest') ?></td>
            <td><span class="badge badge-free"><?= e(ucfirst($l['role'] ?: '—')) ?></span></td>
            <td><code style="font-size:12.5px;"><?= e($l['action']) ?></code></td>
            <td><?= e($l['description']) ?></td>
            <td style="font-size:12.5px;color:var(--color-text-muted);"><?= e($l['ip_address']) ?></td>
            <td style="font-size:12.5px;color:var(--color-text-muted);white-space:nowrap;"><?= time_ago($l['created_at']) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pagination, '/admin/logs.php') ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
