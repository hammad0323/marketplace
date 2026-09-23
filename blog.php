<?php
require __DIR__ . '/config/config.php';

$category = clean($_GET['category'] ?? '');

$where = ["p.status = 'published'"];
$params = [];
$types = '';
if ($category !== '') {
    $where[] = 'p.category = ?';
    $params[] = $category;
    $types .= 's';
}
$whereSql = implode(' AND ', $where);

$countStmt = mysqli_prepare(db(), "SELECT COUNT(*) c FROM blog_posts p WHERE $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRows = mysqli_stmt_get_result($countStmt)->fetch_assoc()['c'];
mysqli_stmt_close($countStmt);
$pagination = paginate($totalRows, 9);

$stmt = mysqli_prepare(db(), "
    SELECT p.*, u.full_name AS author_name, u.avatar AS author_avatar
    FROM blog_posts p JOIN users u ON u.id = p.author_id
    WHERE $whereSql
    ORDER BY p.published_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$posts = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$categories = mysqli_query(db(), "SELECT DISTINCT category FROM blog_posts WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

$pageTitle = ($category !== '' ? $category . ' Articles — ' : 'Blog — ') . SITE_NAME;
$metaDescription = 'Health tips, platform updates, and articles from the ' . SITE_NAME . ' team and our verified doctors.';
$canonical = filtered_canonical('/blog', ['category' => $category], $pagination['page']);
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => 'Blog']];
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Blog</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;max-width:600px;" data-reveal>
            <span class="eyebrow"><?= e(SITE_NAME) ?> Blog</span>
            <h1><?= $category !== '' ? e($category) . ' Articles' : 'Health tips &amp; platform news' ?></h1>
        </div>

        <?php if ($categories): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px;" data-reveal>
            <a href="/blog" class="badge <?= $category === '' ? 'badge-verified' : 'badge-free' ?>">All</a>
            <?php foreach ($categories as $c): ?>
            <a href="/blog?category=<?= urlencode($c['category']) ?>" class="badge <?= $category === $c['category'] ? 'badge-verified' : 'badge-free' ?>"><?= e($c['category']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2 class="sr-only">Latest Posts</h2>
        <?php if (!$posts): ?>
        <div class="card empty-state" data-reveal><i class="ri-quill-pen-line"></i><p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No posts yet</p><p>Check back soon for health tips and platform updates.</p></div>
        <?php else: ?>
        <div class="grid grid-3 stagger">
            <?php foreach ($posts as $p): ?>
            <a href="<?= e(blog_url($p['slug'])) ?>" class="card card-hover" style="overflow:hidden;display:block;" data-reveal>
                <?php if ($p['featured_image']): ?>
                <img src="/uploads/<?= e($p['featured_image']) ?>" alt="<?= e($p['title']) ?>" width="360" height="170" loading="lazy" style="width:100%;height:170px;object-fit:cover;">
                <?php else: ?>
                <div style="width:100%;height:170px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;"><i class="ri-heart-pulse-line"></i></div>
                <?php endif; ?>
                <div style="padding:20px;">
                    <span style="font-size:12px;color:var(--color-text-muted);"><?= format_date($p['published_at']) ?></span>
                    <h3 style="margin:8px 0 10px;line-height:1.4;font-size:17px;"><?= e($p['title']) ?></h3>
                    <?php $postBlocks = get_blog_blocks($p); ?>
                    <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:14px;"><?= e($p['excerpt'] ?: excerpt($postBlocks ? blog_blocks_to_text($postBlocks) : strip_tags($p['content']), 110)) ?></p>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <img src="<?= e(avatar_url($p['author_avatar'], $p['author_name'])) ?>" alt="<?= e($p['author_name']) ?>" width="26" height="26" loading="lazy" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
                        <span style="font-size:13px;font-weight:600;"><?= e($p['author_name']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?= pagination_links($pagination, '/blog' . ($category !== '' ? '?category=' . urlencode($category) : '')) ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
