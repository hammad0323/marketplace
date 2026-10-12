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

// Related posts: same category first, then newest — topic-related, not just recent.
$stmt = mysqli_prepare(db(), "
    SELECT id, slug, title, featured_image, published_at FROM blog_posts
    WHERE status = 'published' AND id != ?
    ORDER BY (category IS NOT NULL AND category != '' AND category = ?) DESC, published_at DESC
    LIMIT 3
");
$postCategory = (string) $post['category'];
mysqli_stmt_bind_param($stmt, 'is', $post['id'], $postCategory);
mysqli_stmt_execute($stmt);
$related = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$blocks = get_blog_blocks($post);
// Doctors for this post's topic, linked with a keyword anchor to the specialty page.
$postSpec = infer_specialization(($blocks ? blog_blocks_to_text($blocks) : (string) $post['content']) . ' ' . $post['category'], $post['title']);
$postDoctors = $postSpec ? doctors_for_specialization((int) $postSpec['id'], 3) : [];
$fallbackText = $post['excerpt'] ?: ($blocks ? blog_blocks_to_text($blocks) : strip_tags($post['content']));
$pageTitle = $post['meta_title'] ?: ($post['title'] . ' — ' . SITE_NAME);
$metaDescription = $post['meta_description'] ?: excerpt($fallbackText, 155);
$ogImage = $post['featured_image'] ? APP_URL . '/uploads/' . $post['featured_image'] : null;
$canonical = APP_URL . blog_url($post['slug']);
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => 'Blog', 'url' => APP_URL . '/blog'], ['name' => $post['title']]];
$extraHead = '<script type="application/ld+json">' . json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'],
    'description' => $metaDescription, 'image' => $ogImage,
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified' => date('c', strtotime($post['published_at'])),
    'author' => ['@type' => 'Person', 'name' => $post['author_name']],
    'publisher' => ['@id' => APP_URL . '/#organization'],
    'mainEntityOfPage' => $canonical,
    'articleSection' => $post['category'] ?: null,
])) . '</script>';
// Content-rich enough to carry ads (see includes/header.php).
$adsEligible = content_word_count($blocks ? blog_blocks_to_text($blocks) : $post['content']) >= 300;
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

        <?php if ($post['category']): ?>
        <span class="badge badge-free" style="margin-bottom:12px;display:inline-block;"><?= e($post['category']) ?></span>
        <?php endif; ?>

        <?php if ($post['featured_image']): ?>
        <img src="/uploads/<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:28px;" data-reveal>
        <?php endif; ?>

        <?php if ($blocks): ?>
        <?php render_blog_blocks($blocks); ?>
        <?php else: ?>
        <div class="rich-content" data-reveal><?= render_rich_html($post['content']) ?></div>
        <?php endif; ?>

        <?php if ($postDoctors): ?>
        <div class="divider-fade"></div>
        <h2 style="margin-bottom:6px;font-size:18px;">Book a <?= e($postSpec['name']) ?> doctor</h2>
        <p style="color:var(--color-text-muted);font-size:14px;margin-bottom:16px;">Verified <?= e($postSpec['name']) ?> specialists on <?= e(get_setting('site_name', SITE_NAME)) ?> — compare fees and book online or in-clinic.</p>
        <div class="grid grid-3 stagger">
            <?php foreach ($postDoctors as $d): require __DIR__ . '/includes/doctor-card.php'; endforeach; ?>
        </div>
        <p style="margin-top:16px;"><a href="/specializations/<?= e($postSpec['slug']) ?>" style="color:var(--color-primary);font-weight:700;">See all <?= e($postSpec['name']) ?> doctors <i class="ri-arrow-right-line"></i></a></p>
        <?php endif; ?>

        <?php if ($related): ?>
        <div class="divider-fade"></div>
        <h2 style="margin-bottom:16px;font-size:16px;">More from the blog</h2>
        <div class="grid grid-3 stagger">
            <?php foreach ($related as $r): ?>
            <a href="<?= e(blog_url($r['slug'])) ?>" class="card card-hover" style="overflow:hidden;display:block;" data-reveal>
                <?php if ($r['featured_image']): ?>
                <img src="/uploads/<?= e($r['featured_image']) ?>" alt="<?= e($r['title']) ?>" width="240" height="120" loading="lazy" style="width:100%;height:120px;object-fit:cover;">
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
