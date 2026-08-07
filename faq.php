<?php
require __DIR__ . '/config/config.php';

$faqs = mysqli_query(db(), 'SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order');

$pageTitle = 'Frequently Asked Questions — ' . SITE_NAME;
$metaDescription = 'Answers to common questions about booking appointments, doctor verification, privacy, and using ' . SITE_NAME . '.';
$extraHead = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
    return ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]];
}, mysqli_fetch_all($faqs, MYSQLI_ASSOC))]) . '</script>';
mysqli_data_seek($faqs, 0);
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/index.php">Home</a> <i class="ri-arrow-right-s-line"></i> <span>FAQs</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;max-width:600px;" data-reveal>
            <span class="eyebrow">Help Center</span>
            <h2>Frequently Asked Questions</h2>
        </div>
        <div style="max-width:760px;" class="stagger">
            <?php $i = 0; while ($f = mysqli_fetch_assoc($faqs)): $i++; ?>
            <div class="card" style="margin-bottom:12px;overflow:hidden;" data-reveal>
                <button type="button" class="faq-q" style="width:100%;text-align:left;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;background:none;border:none;font-weight:700;font-size:15.5px;color:var(--color-text);">
                    <?= e($f['question']) ?>
                    <i class="ri-add-line" style="transition:var(--transition);"></i>
                </button>
                <div class="faq-a" style="max-height:0;overflow:hidden;transition:max-height 0.35s ease;">
                    <p style="padding:0 24px 20px;color:var(--color-text-muted);"><?= e($f['answer']) ?></p>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<script>
document.querySelectorAll('.faq-q').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var answer = btn.nextElementSibling;
        var icon = btn.querySelector('i');
        var isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
        document.querySelectorAll('.faq-a').forEach(function (a) { a.style.maxHeight = '0px'; });
        document.querySelectorAll('.faq-q i').forEach(function (i) { i.style.transform = 'rotate(0deg)'; });
        if (!isOpen) {
            answer.style.maxHeight = answer.scrollHeight + 'px';
            icon.style.transform = 'rotate(45deg)';
        }
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
