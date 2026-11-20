<?php
$PAGE_TITLE = 'Page not found';
require __DIR__ . '/_layout.php';
c_header($PAGE_TITLE);
?>
<section class="c-wrap c-center py-5"><div class="c-empty-icon"><i class="bi bi-signpost-split"></i></div><h1 class="c-h1">Page not found</h1>
<p class="c-muted">Scan the QR code at your print shop to open its page, or enter the shop ID below.</p>
<a class="c-btn" href="<?= e(path('')) ?>">Go to home</a></section>
<?php c_footer();
