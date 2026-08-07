<?php
require __DIR__ . '/config/config.php';
http_response_code(404);
$pageTitle = 'Page Not Found — ' . SITE_NAME;
$metaDescription = 'The page you are looking for could not be found.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 80px);text-align:center;">
    <div class="container">
        <div style="font-size:110px;font-weight:800;background:var(--gradient-primary);-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1;">404</div>
        <h1 style="margin:20px 0 12px;">Page not found</h1>
        <p style="color:var(--color-text-muted);margin-bottom:32px;">The page you're looking for doesn't exist or has moved.</p>
        <a href="/index.php" class="btn btn-primary">Back to Home</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
