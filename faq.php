<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$faqs = wh_fetch_all("SELECT * FROM faqs WHERE business_id=? AND status='active' ORDER BY sort_order", 'i', [$businessId]);

$pageTitle = 'FAQ';
$activeNav = 'faq';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Frequently Asked Questions</h1>
    <p>Everything you need to know before booking.</p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:760px;">
    <div class="reveal">
      <?php foreach ($faqs as $faq): ?>
        <details class="faq-item"><summary><?= e($faq['question']) ?> <i class="fa-solid fa-chevron-down"></i></summary><p><?= e($faq['answer']) ?></p></details>
      <?php endforeach; ?>
      <?php if (!$faqs): ?><p>No FAQs published yet.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
