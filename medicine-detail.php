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

$topDoctors = mysqli_query(db(), "
    SELECT d.slug, d.consultation_fee_online, d.rating_avg, d.rating_count, u.full_name, u.avatar,
        (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS specs
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY d.is_premium DESC, d.rating_avg DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$customFaqs = get_medicine_faqs($medicine['id']);

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

// Custom admin/doctor-authored FAQs take priority; if none were added, fall
// back to auto-generated Q&As from the structured fields so every medicine
// page still carries FAQPage structured data (AEO) and a populated FAQ
// section — visible content and JSON-LD always stay in sync this way.
if ($customFaqs) {
    $displayFaqs = array_map(fn($f) => ['q' => $f['question'], 'a' => strip_tags($f['answer'])], $customFaqs);
} else {
    $displayFaqs = [];
    if ($medicine['uses']) $displayFaqs[] = ['q' => 'What is ' . $medicine['name'] . ' used for?', 'a' => strip_tags($medicine['uses'])];
    if ($medicine['dosage']) $displayFaqs[] = ['q' => 'What is the dosage for ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['dosage'])];
    if ($medicine['side_effects']) $displayFaqs[] = ['q' => 'What are the side effects of ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['side_effects'])];
    if ($medicine['precautions']) $displayFaqs[] = ['q' => 'What precautions should I take with ' . $medicine['name'] . '?', 'a' => strip_tags($medicine['precautions'])];
}
if ($displayFaqs) {
    $extraHead .= '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org', '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $displayFaqs),
    ]) . '</script>';
}

// Section icon/color map for the jump-nav + section headings below.
$sections = array_filter([
    'about' => ['label' => 'About', 'icon' => 'ri-information-line', 'color' => 'var(--color-primary)', 'show' => (bool) $medicine['content']],
    'uses' => ['label' => 'Uses', 'icon' => 'ri-time-line', 'color' => '#0EA5E9', 'show' => (bool) $medicine['uses']],
    'dosage' => ['label' => 'Dosage', 'icon' => 'ri-capsule-line', 'color' => 'var(--color-primary)', 'show' => (bool) $medicine['dosage']],
    'precautions' => ['label' => 'Precautions', 'icon' => 'ri-shield-flash-line', 'color' => '#F59E0B', 'show' => (bool) $medicine['precautions']],
    'side-effects' => ['label' => 'Side Effects', 'icon' => 'ri-alert-line', 'color' => '#A855F7', 'show' => (bool) $medicine['side_effects']],
    'faqs' => ['label' => 'FAQ', 'icon' => 'ri-question-line', 'color' => '#22C55E', 'show' => (bool) $displayFaqs],
], fn($s) => $s['show']);

require __DIR__ . '/includes/header.php';

function med_section_heading($id, $icon, $color, $title)
{
    echo '<div id="' . e($id) . '" style="scroll-margin-top:calc(var(--header-height) + 68px);display:flex;align-items:center;gap:12px;margin-bottom:14px;">'
        . '<span style="width:38px;height:38px;border-radius:12px;background:' . e($color) . ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="' . e($icon) . '"></i></span>'
        . '<h2 style="font-size:19px;">' . e($title) . '</h2></div>';
}
?>
<section class="section" style="padding-top:calc(var(--header-height) + 24px);padding-bottom:0;">
    <div class="container" style="max-width:900px;">
        <nav class="breadcrumb">
            <a href="/">Home</a> <i class="ri-arrow-right-s-line"></i>
            <a href="/medicines">Medicine Information</a> <i class="ri-arrow-right-s-line"></i>
            <span><?= e($medicine['name']) ?></span>
        </nav>
    </div>
</section>

<?php if ($sections): ?>
<nav style="position:sticky;top:var(--header-height);z-index:40;background:var(--color-surface);border-bottom:1px solid var(--color-border);">
    <div class="container" style="max-width:900px;display:flex;gap:4px;overflow-x:auto;padding-top:10px;padding-bottom:10px;">
        <?php foreach ($sections as $id => $s): ?>
        <a href="#<?= e($id) ?>" style="white-space:nowrap;padding:8px 14px;border-radius:var(--radius-full);font-size:13.5px;font-weight:600;color:var(--color-text-muted);flex-shrink:0;"
            onmouseover="this.style.background='rgba(12,107,93,0.08)';this.style.color='var(--color-primary)';"
            onmouseout="this.style.background='';this.style.color='var(--color-text-muted)';"><?= e($s['label']) ?></a>
        <?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>

<article class="section" style="padding-top:32px;">
    <div class="container" style="max-width:900px;">

        <div class="card" style="padding:32px;margin-bottom:32px;display:flex;gap:28px;flex-wrap:wrap;" data-reveal>
            <?php if ($medicine['featured_image']): ?>
            <img src="/uploads/<?= e($medicine['featured_image']) ?>" alt="<?= e($medicine['name']) ?>" style="width:120px;height:120px;border-radius:16px;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
            <div style="width:120px;height:120px;border-radius:16px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:44px;flex-shrink:0;"><i class="ri-capsule-line"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:240px;">
                <h1 style="font-size:27px;margin-bottom:6px;"><?= e($medicine['name']) ?></h1>
                <?php if ($medicine['category']): ?><span class="badge badge-free" style="margin-bottom:14px;display:inline-block;"><?= e($medicine['category']) ?></span><?php endif; ?>
                <div class="grid grid-2" style="gap:8px 24px;font-size:14px;">
                    <?php if ($medicine['generic_name']): ?><div><strong>Generic Name:</strong> <?= e($medicine['generic_name']) ?></div><?php endif; ?>
                    <?php if ($medicine['composition']): ?><div><strong>Composition:</strong> <?= e($medicine['composition']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($medicine['content']): ?>
        <?php med_section_heading('about', 'ri-information-line', 'var(--color-primary)', 'About ' . $medicine['name']); ?>
        <div class="rich-content" style="margin-bottom:36px;" data-reveal><?= $medicine['content'] ?></div>
        <?php endif; ?>

        <?php if ($medicine['uses']): ?>
        <?php med_section_heading('uses', 'ri-time-line', '#0EA5E9', $medicine['name'] . ' Uses'); ?>
        <div class="rich-content" style="margin-bottom:36px;" data-reveal><?= $medicine['uses'] ?></div>
        <?php endif; ?>

        <?php if ($medicine['dosage']): ?>
        <?php med_section_heading('dosage', 'ri-capsule-line', 'var(--color-primary)', $medicine['name'] . ' Dosage &amp; Administration'); ?>
        <div class="rich-content" style="margin-bottom:36px;" data-reveal><?= $medicine['dosage'] ?></div>
        <?php endif; ?>

        <?php if ($medicine['precautions']): ?>
        <?php med_section_heading('precautions', 'ri-shield-flash-line', '#F59E0B', $medicine['name'] . ' Precautions &amp; Warnings'); ?>
        <div class="card" style="padding:20px 24px;margin-bottom:36px;border-left:3px solid #F59E0B;" data-reveal>
            <div class="rich-content" style="margin-bottom:0;"><?= $medicine['precautions'] ?></div>
        </div>
        <?php endif; ?>

        <?php if ($medicine['side_effects']): ?>
        <?php med_section_heading('side-effects', 'ri-alert-line', '#A855F7', $medicine['name'] . ' Side Effects'); ?>
        <div class="rich-content" style="margin-bottom:36px;" data-reveal><?= $medicine['side_effects'] ?></div>
        <?php endif; ?>

        <?php if ($displayFaqs): ?>
        <?php med_section_heading('faqs', 'ri-question-line', '#22C55E', 'Frequently Asked Questions'); ?>
        <div style="margin-bottom:36px;" class="stagger">
            <?php foreach ($displayFaqs as $f): ?>
            <div class="card" style="margin-bottom:12px;overflow:hidden;" data-reveal>
                <button type="button" class="faq-q" style="width:100%;text-align:left;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;background:none;border:none;font-weight:700;font-size:14.5px;color:var(--color-text);">
                    <?= e($f['q']) ?>
                    <i class="ri-add-line" style="transition:var(--transition);flex-shrink:0;"></i>
                </button>
                <div class="faq-a" style="max-height:0;overflow:hidden;transition:max-height 0.35s ease;">
                    <p style="padding:0 22px 18px;color:var(--color-text-muted);font-size:14px;"><?= e($f['a']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p style="font-size:12.5px;color:var(--color-text-muted);padding:16px 18px;background:var(--color-bg);border-radius:var(--radius-sm);margin-bottom:32px;">
            <i class="ri-error-warning-line"></i> This information is educational and not a substitute for professional medical advice.
            Consult a doctor before starting, stopping, or changing any medication.
        </p>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:40px;">
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

        <?php if ($topDoctors): ?>
        <div class="card-gradient-border" style="margin-bottom:40px;" data-reveal>
            <div class="card-inner" style="padding:32px;">
                <h3 style="font-size:18px;margin-bottom:4px;">Consult a Doctor Online</h3>
                <p style="color:var(--color-text-muted);font-size:14px;margin-bottom:20px;">Get expert medical advice before starting any medication — book a verified doctor in minutes.</p>
                <div class="grid grid-3 stagger">
                    <?php foreach ($topDoctors as $d): ?>
                    <a href="<?= e(doctor_url($d['slug'])) ?>" class="card card-hover" style="padding:16px;" data-reveal>
                        <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;">
                            <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                            <div style="min-width:0;">
                                <strong style="display:block;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($d['full_name']) ?></strong>
                                <span style="font-size:12px;color:var(--color-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;"><?= e($d['specs'] ?: 'General Physician') ?></span>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px;">
                            <span><i class="ri-star-fill" style="color:var(--color-warning);"></i> <?= number_format($d['rating_avg'], 1) ?></span>
                            <strong style="color:var(--color-primary);"><?= format_currency($d['consultation_fee_online']) ?></strong>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <a href="/doctors" class="btn btn-primary btn-sm">Browse All Doctors <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
