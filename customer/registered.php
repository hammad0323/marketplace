<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
$verifyUrl = $_SESSION['_just_registered_verify_url'] ?? null;
unset($_SESSION['_just_registered_verify_url']);
$redirectTo = $_GET['redirect_to'] ?? ROUTE_HOME;

$pageTitle = 'Account Created';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card reveal" style="text-align:center;">
    <span class="empty-state-icon">✉️</span>
    <h1>Welcome, <?= mp_e($customer['name']) ?>!</h1>
    <p>Your account is ready. Confirm your email whenever you get a chance.</p>

    <?php if ($verifyUrl): ?>
        <div class="content-panel" style="text-align:left; margin-top:1.5rem;">
            <p style="margin-top:0;"><strong>Confirm your email</strong></p>
            <p style="font-size:.9rem; color:var(--ink-500);">
                Outbound email isn't configured on this install yet, so your verification link is
                shown right here instead of landing in your inbox (it's also written to
                <code>logs/notifications.log</code>).
            </p>
            <p style="word-break:break-all;"><a class="btn btn-sm" href="<?= mp_e($verifyUrl) ?>">Verify My Email</a></p>
        </div>
    <?php endif; ?>

    <a class="btn btn-secondary" style="margin-top:1.5rem;" href="<?= mp_e($redirectTo) ?>">Continue</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
