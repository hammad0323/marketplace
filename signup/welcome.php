<?php
require __DIR__ . '/../config/config.php';

$subdomain = $_GET['subdomain'] ?? '';
$loginUrl = 'https://' . $subdomain . '.' . APP_BASE_DOMAIN . ROUTE_ADMIN . 'login.php';

$pageTitle = 'Marketplace Created';
require __DIR__ . '/../templates/platform-header.php';
?>

<div class="form-card reveal" style="text-align:center;">
    <span class="empty-state-icon">🎉</span>
    <h1>Your marketplace is ready!</h1>
    <p>Your 14-day free trial has started. Log in below to set it up.</p>
    <p style="margin:1.5rem 0;">
        <a class="btn" href="<?= mp_e($loginUrl) ?>"><?= mp_e($subdomain) ?>.<?= mp_e(APP_BASE_DOMAIN) ?></a>
    </p>
    <p style="color:var(--ink-500); font-size:.85rem;">
        Bookmark this link — it's your admin login going forward.
    </p>
</div>

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
