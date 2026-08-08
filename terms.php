<?php
require __DIR__ . '/config/config.php';

$stmt = mysqli_prepare(db(), 'SELECT * FROM cms_pages WHERE slug = ? LIMIT 1');
$slug = 'terms-conditions';
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$page = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$page) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = $page['meta_title'] ?: ($page['title'] . ' — ' . SITE_NAME);
$metaDescription = $page['meta_description'] ?: excerpt($page['content'], 155);
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span><?= e($page['title']) ?></span></nav>
        <div style="max-width:760px;" data-reveal>
            <h1 style="font-size:34px;margin-bottom:8px;"><?= e($page['title']) ?></h1>
            <p style="color:var(--color-text-muted);margin-bottom:28px;">Last updated <?= format_date($page['updated_at']) ?></p>
            <div style="font-size:15.5px;color:var(--color-text-muted);line-height:1.9;"><?= $page['content'] ?></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
