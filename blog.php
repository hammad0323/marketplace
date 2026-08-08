<?php
require __DIR__ . '/config/config.php';

$totalRows = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM blog_posts WHERE status = 'published'"))['c'];
$pagination = paginate($totalRows, 9);

$posts = mysqli_query(db(), "
    SELECT p.*, u.full_name AS author_name, u.avatar AS author_avatar
    FROM blog_posts p JOIN users u ON u.id = p.author_id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Blog — ' . SITE_NAME;
$metaDescription = 'Health tips, platform updates, and articles from the ' . SITE_NAME . ' team and our verified doctors.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Blog</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;max-width:600px;" data-reveal>
            <span class="eyebrow">MediConnect Blog</span>
            <h2>Health tips &amp; platform news</h2>
        </div>

        <?php if (!$posts): ?>
        <div class="card empty-state" data-reveal><i class="ri-quill-pen-line"></i><h4>No posts yet</h4><p>Check back soon for health tips and platform updates.</p></div>
        <?php else: ?>
        <div class="grid grid-3 stagger">
            <?php foreach ($posts as $p): ?>
            <a href="/blog-post?slug=<?= e($p['slug']) ?>" class="card card-hover" style="overflow:hidden;display:block;" data-reveal>
                <?php if ($p['featured_image']): ?>
                <img src="/uploads/<?= e($p['featured_image']) ?>" alt="<?= e($p['title']) ?>" style="width:100%;height:170px;object-fit:cover;">
                <?php else: ?>
                <div style="width:100%;height:170px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;"><i class="ri-heart-pulse-line"></i></div>
                <?php endif; ?>
                <div style="padding:20px;">
                    <span style="font-size:12px;color:var(--color-text-muted);"><?= format_date($p['published_at']) ?></span>
                    <h4 style="margin:8px 0 10px;line-height:1.4;"><?= e($p['title']) ?></h4>
                    <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:14px;"><?= e($p['excerpt'] ?: excerpt(strip_tags($p['content']), 110)) ?></p>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <img src="<?= e(avatar_url($p['author_avatar'], $p['author_name'])) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
                        <span style="font-size:13px;font-weight:600;"><?= e($p['author_name']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?= pagination_links($pagination, '/blog') ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
