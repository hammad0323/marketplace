<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
$verifyUrl = $_SESSION['_just_registered_verify_url'] ?? null;
unset($_SESSION['_just_registered_verify_url']);

$pageTitle = 'Registration Received';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card form-card-wide reveal" style="text-align:center;">
    <span class="empty-state-icon">✉️</span>
    <h1>Welcome, <?= mp_e($vendor['store_name']) ?>!</h1>
    <p>Your application is in for review. Meanwhile, confirm your email address to
       unlock every feature once your store is approved.</p>

    <?php if ($verifyUrl): ?>
        <div class="content-panel" style="text-align:left; margin-top:1.5rem;">
            <p style="margin-top:0;"><strong>Confirm your email</strong></p>
            <p style="font-size:.9rem; color:var(--ink-500);">
                Outbound email isn't configured on this install yet, so instead of landing in your
                inbox, your verification link is shown right here (it's also written to
                <code>logs/notifications.log</code> the same way a real email send will be once
                PHPMailer is wired up).
            </p>
            <p style="word-break:break-all;"><a class="btn btn-sm" href="<?= mp_e($verifyUrl) ?>">Verify My Email</a></p>
        </div>
    <?php endif; ?>

    <a class="btn btn-secondary" style="margin-top:1.5rem;" href="dashboard.php">Go to My Dashboard</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
