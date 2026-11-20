<?php
require __DIR__ . '/_layout.php';
c_header('Privacy', ['description' => 'How we handle your documents']);
?>
<section class="c-narrow"><h1 class="c-h1">Your files &amp; privacy</h1>
<div class="c-card"><p><?= nl2br(e(setting('privacy_notice'))) ?></p>
<ul class="mb-0"><li>Files are stored privately and are only visible to the shop you send them to.</li>
<li>Download links for files expire after a short time.</li>
<li>Files are deleted automatically after the shop’s retention period (usually a few days after your order is closed), and unused uploads within <?= (int) setting('temp_upload_hours', 24) ?> hours.</li>
<li>Your original file is never changed — edits create a separate print-ready copy.</li>
<li>We do not sell your data or use your documents to train AI.</li>
<li>You don’t need an account. This device keeps a private session so you can see your orders.</li></ul></div>
<?php if (setting('support_email') || setting('support_phone')): ?><p class="c-muted small">Questions: <?= e(trim(setting('support_email') . ' ' . setting('support_phone'))) ?></p><?php endif; ?>
</section>
<?php c_footer();
