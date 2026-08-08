<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "
    SELECT p.*, u.full_name AS author_name, u.avatar AS author_avatar
    FROM blog_posts p JOIN users u ON u.id = p.author_id
    WHERE p.slug = ? AND p.status = 'published' LIMIT 1
");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$post = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$related = mysqli_query(db(), "
    SELECT id, slug, title, featured_image, published_at FROM blog_posts
    WHERE status = 'published' AND id != " . (int) $post['id'] . "
    ORDER BY published_at DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = $post['title'] . ' — ' . SITE_NAME;
$metaDescription = excerpt($post['excerpt'] ?: strip_tags($post['content']), 155);
$ogImage = $post['featured_image'] ? APP_URL . '/uploads/' . $post['featured_image'] : null;
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'],
    'datePublished' => $post['published_at'], 'author' => ['@type' => 'Person', 'name' => $post['author_name']],
]) . '</script>';
require __DIR__ . '/includes/header.php';
?>
<article class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container" style="max-width:760px;">
        <nav class="breadcrumb">
            <a href="/">Home</a> <i class="ri-arrow-right-s-line"></i>
            <a href="/blog">Blog</a> <i class="ri-arrow-right-s-line"></i>
            <span><?= e($post['title']) ?></span>
        </nav>

        <h1 style="margin-bottom:16px;font-size:34px;line-height:1.3;" data-reveal><?= e($post['title']) ?></h1>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;" data-reveal>
            <img src="<?= e(avatar_url($post['author_avatar'], $post['author_name'])) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
            <div>
                <strong style="display:block;font-size:14px;"><?= e($post['author_name']) ?></strong>
                <span style="font-size:12.5px;color:var(--color-text-muted);"><?= format_date($post['published_at']) ?></span>
            </div>
        </div>

        <?php if ($post['featured_image']): ?>
        <img src="/uploads/<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:28px;" data-reveal>
        <?php endif; ?>

        <div class="rich-content" data-reveal><?= $post['content'] ?></div>

        <?php if ($related): ?>
        <div class="divider-fade"></div>
        <h4 style="margin-bottom:16px;">More from the blog</h4>
        <div class="grid grid-3 stagger">
            <?php foreach ($related as $r): ?>
            <a href="/blog-post?slug=<?= e($r['slug']) ?>" class="card card-hover" style="overflow:hidden;display:block;" data-reveal>
                <?php if ($r['featured_image']): ?>
                <img src="/uploads/<?= e($r['featured_image']) ?>" alt="" style="width:100%;height:120px;object-fit:cover;">
                <?php else: ?>
                <div style="width:100%;height:120px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;"><i class="ri-heart-pulse-line"></i></div>
                <?php endif; ?>
                <div style="padding:14px;">
                    <strong style="font-size:13.5px;line-height:1.4;display:block;"><?= e($r['title']) ?></strong>
                    <span style="font-size:11.5px;color:var(--color-text-muted);"><?= format_date($r['published_at']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
