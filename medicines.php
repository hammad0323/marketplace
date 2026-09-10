<?php
require __DIR__ . '/config/config.php';

$q = clean($_GET['q'] ?? '');
$category = clean($_GET['category'] ?? '');
$letter = strtoupper(clean($_GET['letter'] ?? ''));
if (!preg_match('/^[A-Z]$/', $letter)) {
    $letter = '';
}

$where = ["status = 'published'"];
$params = [];
$types = '';
$boolQuery = '';

if ($q !== '') {
    $words = preg_split('/\s+/', trim($q));
    $boolQuery = implode(' ', array_map(fn($w) => '+' . preg_replace('/[+\-<>()~*"@]/', '', $w) . '*', array_filter($words)));
    $where[] = 'MATCH(name, generic_name, uses, content) AGAINST (? IN BOOLEAN MODE)';
    $params[] = $boolQuery;
    $types .= 's';
}
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
    $types .= 's';
}
if ($letter !== '') {
    $where[] = 'name LIKE ?';
    $params[] = $letter . '%';
    $types .= 's';
}

$whereSql = implode(' AND ', $where);
$useRelevanceOrder = $q !== '';
$orderSql = $useRelevanceOrder ? 'MATCH(name, generic_name, uses, content) AGAINST (? IN BOOLEAN MODE) DESC' : 'created_at DESC';

$countSql = "SELECT COUNT(*) c FROM medicine_info WHERE $whereSql";
$stmt = mysqli_prepare(db(), $countSql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
mysqli_stmt_close($stmt);

$pagination = paginate($total, 12);

$listSql = "SELECT id, name, slug, generic_name, category, uses, featured_image FROM medicine_info WHERE $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
$stmt = mysqli_prepare(db(), $listSql);
$orderParams = $useRelevanceOrder ? [$boolQuery] : [];
$allTypes = $types . ($useRelevanceOrder ? 's' : '') . 'ii';
$allParams = array_merge($params, $orderParams, [$pagination['per_page'], $pagination['offset']]);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$medicines = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$categories = mysqli_query(db(), "SELECT DISTINCT category FROM medicine_info WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

$pageTitle = ($q !== '' ? 'Search: ' . $q . ' — ' : ($letter !== '' ? $letter . ' — ' : '')) . 'Medicine Information — ' . SITE_NAME;
$metaDescription = 'Search dosage, uses, side effects, and precautions for medicines, contributed by verified doctors on ' . SITE_NAME . '.';
$canonical = filtered_canonical('/medicines', ['q' => $q, 'category' => $category, 'letter' => $letter]);
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);padding-bottom:0;">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Medicine Information</span></nav>
        <h1 style="font-size:32px;margin-bottom:8px;">Medicine Information</h1>
        <p style="color:var(--color-text-muted);margin-bottom:32px;">Search dosage, uses, side effects, and precautions — contributed by our doctors.</p>

        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;max-width:640px;">
            <?php if ($letter !== ''): ?><input type="hidden" name="letter" value="<?= e($letter) ?>"><?php endif; ?>
            <input type="text" name="q" class="form-control" placeholder="Search a medicine name…" value="<?= e($q) ?>" style="flex:1;min-width:220px;">
            <?php if ($categories): ?>
            <select name="category" class="form-control" style="max-width:200px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= e($c['category']) ?>" <?= $category === $c['category'] ? 'selected' : '' ?>><?= e($c['category']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <?php
        $letterQs = $_GET;
        unset($letterQs['letter'], $letterQs['page']);
        $letterBase = '/medicines' . ($letterQs ? '?' . http_build_query($letterQs) . '&' : '?');
        ?>
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:28px;" role="navigation" aria-label="Filter by first letter">
            <a href="/medicines<?= $letterQs ? '?' . http_build_query($letterQs) : '' ?>" class="badge <?= $letter === '' ? 'badge-verified' : 'badge-free' ?>" style="min-width:34px;text-align:center;"<?= $letter === '' ? ' aria-current="true"' : '' ?>>All</a>
            <?php foreach (range('A', 'Z') as $L): ?>
            <a href="<?= e($letterBase . 'letter=' . $L) ?>" class="badge <?= $letter === $L ? 'badge-verified' : 'badge-free' ?>" style="min-width:28px;text-align:center;"<?= $letter === $L ? ' aria-current="true"' : '' ?>><?= $L ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container">
        <?php if ($total === 0): ?>
        <div class="empty-state card">
            <i class="ri-capsule-line"></i>
            <h4>No medicine information found</h4>
            <p>Try a different search term or clear the category filter.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-3 stagger">
            <?php foreach ($medicines as $m): ?>
            <a href="<?= e(medicine_url($m['slug'])) ?>" class="card card-hover" style="padding:20px;display:block;" data-reveal>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
                    <?php if ($m['featured_image']): ?>
                    <img src="/uploads/<?= e($m['featured_image']) ?>" alt="" style="width:44px;height:44px;border-radius:10px;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                    <div style="width:44px;height:44px;border-radius:10px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;"><i class="ri-capsule-line"></i></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <strong style="display:block;font-size:14.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($m['name']) ?></strong>
                        <?php if ($m['generic_name']): ?><span style="font-size:12px;color:var(--color-text-muted);"><?= e($m['generic_name']) ?></span><?php endif; ?>
                    </div>
                </div>
                <?php if ($m['category']): ?><span class="badge badge-free" style="margin-bottom:8px;"><?= e($m['category']) ?></span><?php endif; ?>
                <p style="font-size:13px;color:var(--color-text-muted);"><?= e(excerpt($m['uses'] ?? '', 90)) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php
        $pageQs = $_GET; unset($pageQs['page']);
        echo pagination_links($pagination, '/medicines' . ($pageQs ? '?' . http_build_query($pageQs) : ''));
        ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
