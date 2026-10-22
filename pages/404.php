<?php
http_response_code(404);
seo_set(['title' => 'Page not found', 'noindex' => true]);
$bodyClass = 'page-404';
require UC_ROOT . '/includes/header.php';
$suggest = new_arrivals(4);
?>
<section class="uc-container uc-404">
    <p class="uc-eyebrow">Error 404</p>
    <h1 class="uc-display">This page has stepped out.</h1>
    <p class="lead">The page you are looking for may have moved or no longer exists.</p>
    <form action="<?= e(url('search')) ?>" class="uc-404-search" role="search">
        <input type="search" name="q" placeholder="Search the store" aria-label="Search">
        <button class="uc-btn" type="submit">Search</button>
    </form>
    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
        <a class="uc-btn uc-btn--outline" href="<?= e(url()) ?>">Back to Home</a>
        <a class="uc-btn uc-btn--outline" href="<?= e(url('new-arrivals')) ?>">New Arrivals</a>
    </div>
    <?php if ($suggest): ?>
        <h2 class="uc-section-title mt-5 pt-4">You might like</h2>
        <div class="uc-grid uc-grid--4"><?php foreach ($suggest as $p) partial('components/product-card', ['p' => $p]); ?></div>
    <?php endif; ?>
</section>
<?php require UC_ROOT . '/includes/footer.php';
