<?php
if (!isset($GLOBALS['db'])) {
    require __DIR__ . '/config/config.php';
}
http_response_code(403);
$pageTitle = 'Access Denied — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 80px);text-align:center;">
    <div class="container">
        <i class="ri-shield-cross-line" style="font-size:80px;color:var(--color-danger);"></i>
        <h1 style="margin:20px 0 12px;">Access denied</h1>
        <p style="color:var(--color-text-muted);margin-bottom:32px;">You don't have permission to view this page with your current account.</p>
        <a href="/index.php" class="btn btn-primary">Back to Home</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
