<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$activity = mp_recent_activity(100);

$pageTitle = 'Activity Log';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Activity Log</h1>
<p style="color:var(--ink-500); margin-top:-.5rem;">The most recent 100 platform events.</p>

<div class="admin-panel">
    <?php if (!$activity): ?>
        <p>No activity recorded yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($activity as $entry): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= mp_e(date('M j, Y g:i A', strtotime($entry['created_at']))) ?></td>
                    <td><span class="badge"><?= mp_e(ucfirst($entry['actor_type'])) ?><?= $entry['actor_id'] ? ' #' . (int) $entry['actor_id'] : '' ?></span></td>
                    <td><?= mp_e($entry['action']) ?></td>
                    <td><?= mp_e($entry['description'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
