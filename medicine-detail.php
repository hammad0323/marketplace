<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "
    SELECT m.*, u.full_name AS author_name, u.avatar AS author_avatar, u.role AS author_role,
        d.slug AS doctor_slug
    FROM medicine_info m
    JOIN users u ON u.id = m.author_id
    LEFT JOIN doctors d ON d.user_id = u.id
    WHERE m.slug = ? AND m.status = 'published' LIMIT 1
");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$medicine = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$medicine) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$related = mysqli_query(db(), "
    SELECT name, slug, featured_image FROM medicine_info
    WHERE status = 'published' AND id != " . (int) $medicine['id'] . "
    " . ($medicine['category'] ? "AND category = '" . mysqli_real_escape_string(db(), $medicine['category']) . "'" : '') . "
    ORDER BY created_at DESC LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = $medicine['meta_title'] ?: ($medicine['name'] . ' — Uses, Dosage &amp; Side Effects | ' . SITE_NAME);
$metaDescription = $medicine['meta_description'] ?: excerpt($medicine['uses'] ?: strip_tags($medicine['content']), 155);
$ogImage = $medicine['featured_image'] ? APP_URL . '/uploads/' . $medicine['featured_image'] : null;
$canonical = APP_URL . medicine_url($medicine['slug']);
$extraHead = '<script type="application/ld+json">' . json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Drug', 'name' => $medicine['name'],
    'nonProprietaryName' => $medicine['generic_name'],
    'activeIngredient' => $medicine['composition'],
    'description' => strip_tags($medicine['content'] ?: $medicine['uses'] ?: ''),
    'drugClass' => $medicine['category'],
])) . '</script>';

// FAQPage from the existing uses/dosage/side-effects/precautions fields —
// zero new admin input needed, and it lets AI answer engines quote a crisp
// Q&A instead of parsing the full article (AEO).
$medFaq = [];
if ($medicine['uses']) $medFaq[] = ['q' => 'What is ' . $medicine['name'] . ' used for?', 'a' => strip_tags($medicine['uses'])];
if ($medicine['dosage']) $medFaq[] = ['q' => 'What is the dosage for ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['dosage'])];
if ($medicine['side_effects']) $medFaq[] = ['q' => 'What are the side effects of ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['side_effects'])];
if ($medicine['precautions']) $medFaq[] = ['q' => 'What precautions should I take with ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['precautions'])];
if ($medFaq) {
    $extraHead .= '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org', '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $medFaq),
    ]) . '</script>';
}
require __DIR__ . '/includes/header.php';
?>
<article class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container" style="max-width:820px;">
        <nav class="breadcrumb">
            <a href="/">Home</a> <i class="ri-arrow-right-s-line"></i>
            <a href="/medicines">Medicine Information</a> <i class="ri-arrow-right-s-line"></i>
            <span><?= e($medicine['name']) ?></span>
        </nav>

        <div style="display:flex;gap:16px;align-items:center;margin-bottom:8px;" data-reveal>
            <?php if ($medicine['featured_image']): ?>
            <img src="/uploads/<?= e($medicine['featured_image']) ?>" alt="<?= e($medicine['name']) ?>" style="width:64px;height:64px;border-radius:14px;object-fit:cover;">
            <?php else: ?>
            <div style="width:64px;height:64px;border-radius:14px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;"><i class="ri-capsule-line"></i></div>
            <?php endif; ?>
            <div>
                <h1 style="font-size:28px;margin-bottom:4px;"><?= e($medicine['name']) ?></h1>
                <?php if ($medicine['generic_name']): ?><p style="color:var(--color-text-muted);font-size:14px;">Generic name: <?= e($medicine['generic_name']) ?></p><?php endif; ?>
            </div>
        </div>
        <?php if ($medicine['category']): ?><span class="badge badge-free" style="margin-bottom:24px;display:inline-block;"><?= e($medicine['category']) ?></span><?php endif; ?>

        <div class="grid grid-2 stagger" style="margin-bottom:24px;">
            <?php if ($medicine['composition']): ?>
            <div class="card" style="padding:20px;" data-reveal><strong style="display:block;margin-bottom:6px;font-size:13px;color:var(--color-text-muted);text-transform:uppercase;">Composition</strong><p><?= e($medicine['composition']) ?></p></div>
            <?php endif; ?>
            <?php if ($medicine['dosage']): ?>
            <div class="card" style="padding:20px;" data-reveal><strong style="display:block;margin-bottom:6px;font-size:13px;color:var(--color-text-muted);text-transform:uppercase;">Dosage</strong><p><?= nl2br(e($medicine['dosage'])) ?></p></div>
            <?php endif; ?>
            <?php if ($medicine['uses']): ?>
            <div class="card" style="padding:20px;" data-reveal><strong style="display:block;margin-bottom:6px;font-size:13px;color:var(--color-text-muted);text-transform:uppercase;">Uses</strong><p><?= nl2br(e($medicine['uses'])) ?></p></div>
            <?php endif; ?>
            <?php if ($medicine['side_effects']): ?>
            <div class="card" style="padding:20px;" data-reveal><strong style="display:block;margin-bottom:6px;font-size:13px;color:var(--color-text-muted);text-transform:uppercase;">Side Effects</strong><p><?= nl2br(e($medicine['side_effects'])) ?></p></div>
            <?php endif; ?>
        </div>

        <?php if ($medicine['precautions']): ?>
        <div class="card" style="padding:20px;margin-bottom:24px;border-left:3px solid var(--color-warning);" data-reveal>
            <strong style="display:block;margin-bottom:6px;font-size:13px;color:var(--color-warning);text-transform:uppercase;"><i class="ri-alert-line"></i> Precautions &amp; Warnings</strong>
            <p><?= nl2br(e($medicine['precautions'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="rich-content" data-reveal><?= $medicine['content'] ?></div>

        <div class="divider-fade"></div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
            <img src="<?= e(avatar_url($medicine['author_avatar'], $medicine['author_name'])) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
            <div>
                <span style="font-size:12.5px;color:var(--color-text-muted);">Contributed by</span>
                <?php if ($medicine['author_role'] === 'doctor' && $medicine['doctor_slug']): ?>
                <a href="<?= e(doctor_url($medicine['doctor_slug'])) ?>" style="display:block;font-weight:700;"><?= e($medicine['author_name']) ?></a>
                <?php else: ?>
                <strong style="display:block;"><?= e($medicine['author_name']) ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:24px;">
            This information is educational and not a substitute for professional medical advice. Consult a
            doctor before starting, stopping, or changing any medication.
        </p>

        <?php if ($related): ?>
        <h4 style="margin-bottom:16px;">Related medicines</h4>
        <div class="grid grid-4 stagger">
            <?php foreach ($related as $r): ?>
            <a href="<?= e(medicine_url($r['slug'])) ?>" class="card card-hover" style="padding:14px;text-align:center;" data-reveal>
                <?php if ($r['featured_image']): ?>
                <img src="/uploads/<?= e($r['featured_image']) ?>" alt="" style="width:40px;height:40px;border-radius:10px;object-fit:cover;margin:0 auto 8px;">
                <?php else: ?>
                <div style="width:40px;height:40px;border-radius:10px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;margin:0 auto 8px;"><i class="ri-capsule-line"></i></div>
                <?php endif; ?>
                <strong style="font-size:13px;display:block;"><?= e($r['name']) ?></strong>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
