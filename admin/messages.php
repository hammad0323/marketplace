<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$messages = mysqli_query(db(), 'SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100');

$pageTitle = 'Messages';
$heading = 'Contact Messages';
$extraScripts = '<script src="/assets/js/admin-messages.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<?php if (mysqli_num_rows($messages) === 0): ?>
<div class="empty-state card"><i class="ri-mail-line"></i><h4>No messages yet</h4></div>
<?php else: ?>
<div class="stagger">
<?php while ($m = mysqli_fetch_assoc($messages)): ?>
<div class="card" style="padding:22px;margin-bottom:14px;" data-reveal data-msg-id="<?= (int)$m['id'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <div>
            <strong><?= e($m['name']) ?></strong> <span style="color:var(--color-text-muted);font-size:13px;">&lt;<?= e($m['email']) ?>&gt;</span>
            <?php if ($m['phone']): ?><span style="color:var(--color-text-muted);font-size:13px;"> &middot; <?= e($m['phone']) ?></span><?php endif; ?>
            <div style="font-weight:700;margin-top:6px;"><?= e($m['subject']) ?></div>
            <p style="color:var(--color-text-muted);margin-top:6px;max-width:640px;"><?= nl2br(e($m['message'])) ?></p>
            <span style="font-size:12px;color:var(--color-text-muted);"><?= time_ago($m['created_at']) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="status-pill status-<?= $m['status'] === 'new' ? 'pending' : 'active' ?>"><?= ucfirst($m['status']) ?></span>
            <select class="form-control btn-msg-status" style="width:130px;">
                <option value="new" <?= $m['status'] === 'new' ? 'selected' : '' ?>>New</option>
                <option value="read" <?= $m['status'] === 'read' ? 'selected' : '' ?>>Read</option>
                <option value="replied" <?= $m['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
            </select>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
